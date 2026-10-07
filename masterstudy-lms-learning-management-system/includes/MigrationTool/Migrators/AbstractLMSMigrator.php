<?php

namespace MasterStudy\Lms\MigrationTool\Migrators;

defined( 'ABSPATH' ) || exit;

use MasterStudy\Lms\MigrationTool\Contracts\MigratorInterface;

/**
 * Base class for all LMS migrators. Concrete migrators implement get_lms_class()
 * and the identity methods; all delegation logic lives here.
 */
abstract class AbstractLMSMigrator implements MigratorInterface {

	/**
	 * Fully-qualified class name of the LMS static helper (LMS/*.php).
	 *
	 * @return class-string
	 */
	abstract protected static function get_lms_class(): string;

	/**
	 * Default: every step is available. Migrators with steps backed by a separate
	 * source Pro plugin override this to gate those steps on the Pro plugin being active.
	 */
	public function is_step_available( string $step ): bool {
		/**
		 * Filters whether a migration step's source data may be migrated.
		 *
		 * @param bool                 $available Whether the step may be migrated.
		 * @param string               $step      Step name.
		 * @param string               $slug      Migrator slug, e.g. 'tutor'.
		 * @param AbstractLMSMigrator  $migrator  The migrator.
		 */
		return (bool) apply_filters( 'masterstudy_lms_migration_tool_is_step_available', true, $step, $this->get_slug(), $this );
	}

	/**
	 * Loads wp-admin/includes/plugin.php on demand so the check is reliable inside
	 * REST requests and background jobs where it may not yet be loaded.
	 */
	public function is_source_plugin_active( string $plugin_file ): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		/**
		 * Filters whether the source LMS counts as active, i.e. whether it can be selected for a migration.
		 *
		 * @param bool                $active      Whether the source plugin is active.
		 * @param string              $plugin_file Source plugin basename.
		 * @param string              $slug        Migrator slug, e.g. 'tutor'.
		 * @param AbstractLMSMigrator $migrator    The migrator.
		 */
		return (bool) apply_filters( 'masterstudy_lms_migration_tool_is_source_plugin_active', is_plugin_active( $plugin_file ), $plugin_file, $this->get_slug(), $this );
	}

	public function count_source_items( string $step ): int {
		if ( ! $this->is_step_available( $step ) ) {
			return 0;
		}

		return (int) apply_filters(
			'masterstudy_lms_migration_tool_count_source_items',
			( static::get_lms_class() )::count_source_items( $step ),
			$step,
			$this->get_slug()
		);
	}

	public function get_source_ids( string $step, int $limit, int $cursor, array $exclude = array() ): array {
		if ( ! $this->is_step_available( $step ) ) {
			return array();
		}

		return array_map(
			'intval',
			(array) apply_filters(
				'masterstudy_lms_migration_tool_source_ids',
				( static::get_lms_class() )::get_source_ids( $step, $limit, $cursor, $exclude ),
				$step,
				$limit,
				$cursor,
				$exclude,
				$this->get_slug()
			)
		);
	}

	public function migrate_item( string $step, int $item_id ): void {
		( static::get_lms_class() )::migrate_item( $step, $item_id );

		/**
		 * Fires after the shared migration of one item. A contributed step is migrated here.
		 *
		 * @param string $step    Step name.
		 * @param int    $item_id Source item ID.
		 * @param string $slug    Migrator slug, e.g. 'tutor'.
		 */
		do_action( 'masterstudy_lms_migration_tool_migrate_item', $step, $item_id, $this->get_slug() );
	}

	public function finalize_step( string $step ): void {
		( static::get_lms_class() )::finalize_step( $step );
	}

	public function get_addons_to_activate( string $step ): array {
		return array();
	}

	/**
	 * Source LMS features that MasterStudy cannot import (shown on the source card before
	 * the migration starts). Items of these kinds end up in the migration report.
	 *
	 * @return string[] Human-readable feature names.
	 */
	public function get_unsupported(): array {
		return (array) apply_filters( 'masterstudy_lms_migration_tool_unsupported', $this->unsupported_features(), $this->get_slug() );
	}

	/**
	 * @return string[]
	 */
	protected function unsupported_features(): array {
		return array();
	}

	/**
	 * Source post types counted in the preview, by content key (courses, lessons, quizzes, questions, assignments).
	 *
	 * @return array<string, string[]>
	 */
	protected function content_post_types(): array {
		return array();
	}

	/**
	 * Item count of a step for the preview. Steps that only count records of already copied courses (they are 0
	 * before the first migration) override this to count the source records.
	 */
	protected function preview_count( string $step ): int {
		return $this->count_source_items( $step );
	}

	/**
	 * Extra preview counts that are not posts (e.g. questions stored in a custom table).
	 *
	 * @return array<string, int>
	 */
	protected function extra_content_counts(): array {
		return array();
	}

	/**
	 * What a migration would copy: source content counts and the item count of every step with its
	 * "Choose what to migrate" group. Nothing is written.
	 *
	 * @return array{ content: array<string, int>, steps: array<int, array{ step: string, group: string, total: int }> }
	 */
	public function preview(): array {
		global $wpdb;

		$content = array();

		foreach ( $this->content_post_types() as $key => $types ) {
			$types = array_values( (array) $types );

			if ( ! $types ) {
				continue;
			}

			$placeholders    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
			$content[ $key ] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ({$placeholders}) AND post_status NOT IN ( 'trash', 'auto-draft' )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$types
				)
			);
		}

		$content = array_merge( $content, $this->extra_content_counts() );
		$steps   = array();

		foreach ( $this->get_steps() as $step ) {
			try {
				$total = $this->preview_count( $step );
			} catch ( \Throwable $e ) {
				$total = 0;
			}

			$steps[] = array(
				'step'  => $step,
				'group' => \MasterStudy\Lms\MigrationTool\Helper::step_group( $step ),
				'total' => (int) $total,
			);
		}

		/**
		 * Filters the migration preview of a source LMS.
		 *
		 * @param array  $preview Preview data.
		 * @param string $slug    Migrator slug.
		 */
		return (array) apply_filters(
			'masterstudy_lms_migration_tool_preview',
			array(
				'content' => $content,
				'steps'   => $steps,
			),
			$this->get_slug()
		);
	}

	/**
	 * Apply the addon filter to a step => addon map.
	 *
	 * @param array<string, string> $map Step name => MasterStudy addon slug.
	 */
	protected function filter_addons( array $map, string $step ): array {
		return (array) apply_filters(
			'masterstudy_lms_migration_tool_addons_to_activate',
			isset( $map[ $step ] ) ? array( $map[ $step ] ) : array(),
			$step,
			$this->get_slug()
		);
	}

	/**
	 * Apply the steps filter.
	 *
	 * @param string[] $steps Ordered step names.
	 */
	protected function filter_steps( array $steps ): array {
		/**
		 * Filters the ordered migration steps for a migrator. Contributed steps are appended after the shared ones.
		 *
		 * @param string[] $steps Ordered step names.
		 * @param string   $slug  Migrator slug, e.g. 'tutor'.
		 */
		return (array) apply_filters( 'masterstudy_lms_migration_tool_steps', $steps, $this->get_slug() );
	}
}
