<?php

namespace MasterStudy\Lms\MigrationTool\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * All LMS migrators must implement this interface so the controllers
 * can treat every LMS identically — no LMS-specific branching in the controllers.
 */
interface MigratorInterface {

	/**
	 * Unique slug that matches the LMS plugin directory key.
	 *
	 * @return string e.g. 'learnpress', 'sfwd-lms', 'tutor'
	 */
	public function get_slug(): string;

	/**
	 * Human-readable label shown in the UI.
	 *
	 * @return string e.g. 'LearnPress'
	 */
	public function get_label(): string;

	/**
	 * Plugin file used by is_plugin_active() to detect installation.
	 *
	 * @return string e.g. 'learnpress/learnpress.php'
	 */
	public function get_plugin_file(): string;

	/**
	 * Ordered list of migration step names.
	 *
	 * @return string[] e.g. ['courses', 'orders', 'reviews']
	 */
	public function get_steps(): array;

	/**
	 * Whether the given step's source data may be migrated.
	 *
	 * Free/core steps are always available. Steps backed by a separate source Pro
	 * plugin (e.g. Tutor Pro) are available only while that Pro plugin is active —
	 * when it is deactivated its data is treated as absent so the step is skipped
	 * cleanly (count 0) rather than half-migrated.
	 *
	 * @param string $step Step name.
	 */
	public function is_step_available( string $step ): bool;

	/**
	 * Count total source items for a step. Fast COUNT query — no records loaded.
	 *
	 * @param string $step Step name.
	 */
	public function count_source_items( string $step ): int;

	/**
	 * Return one batch of source IDs starting after $cursor.
	 *
	 * The migration copies data and never modifies or deletes source records, so every step
	 * is cursor based: WHERE id > $cursor ORDER BY id LIMIT $limit.
	 *
	 * $exclude contains IDs that have already failed; cursor-based steps can ignore it.
	 *
	 * @param string $step    Step name.
	 * @param int    $limit   Batch size.
	 * @param int    $cursor  Last processed ID (0 = first batch).
	 * @param int[]  $exclude IDs to skip (already-failed items).
	 * @return int[]
	 */
	public function get_source_ids( string $step, int $limit, int $cursor, array $exclude = array() ): array;

	/**
	 * Migrate exactly one item. Called inside START TRANSACTION / COMMIT.
	 * Must be idempotent — safe to call twice for the same item_id.
	 *
	 * @param string $step    Step name.
	 * @param int    $item_id Source item ID.
	 * @throws \Exception Triggers ROLLBACK; item added to failed list.
	 */
	public function migrate_item( string $step, int $item_id ): void;

	/**
	 * Called once after a step fully completes. Perform bulk recalculation here
	 * instead of per-item. No-op by default.
	 *
	 * @param string $step Step name.
	 */
	public function finalize_step( string $step ): void;

	/**
	 * Return MasterStudy addon slugs (keys of the `stm_lms_addons` option) to enable
	 * when this step has data to migrate.
	 *
	 * Called once at step start (cursor = 0, completed = 0) only when item count > 0.
	 *
	 * @param string $step Step name.
	 * @return string[] e.g. ['assignments', 'google_meet'].
	 */
	public function get_addons_to_activate( string $step ): array;
}
