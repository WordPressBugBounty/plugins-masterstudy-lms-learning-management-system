<?php

namespace MasterStudy\Lms\MigrationTool;

defined( 'ABSPATH' ) || exit;

use MasterStudy\Lms\MigrationTool\Contracts\MigratorInterface;

/**
 * Holds all registered LMS migrators. The controllers resolve the requested
 * migrator by slug and delegate — with no knowledge of any specific LMS.
 *
 * Adding a new LMS requires only:
 *   1. A new class in Migrators/ implementing MigratorInterface.
 *   2. One `register()` call, typically via the `masterstudy_lms_migration_tool_register` filter.
 */
class MigratorRegistry {

	/**
	 * @var MigratorInterface[]
	 */
	private $migrators = array();

	public function register( MigratorInterface $migrator ): self {
		$this->migrators[ $migrator->get_slug() ] = $migrator;

		return $this;
	}

	public function get( string $slug ): ?MigratorInterface {
		return $this->migrators[ $slug ] ?? null;
	}

	/**
	 * @return MigratorInterface[]
	 */
	public function all(): array {
		return $this->migrators;
	}

	public function has( string $slug ): bool {
		return isset( $this->migrators[ $slug ] );
	}
}
