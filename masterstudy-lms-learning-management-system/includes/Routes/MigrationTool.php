<?php

use MasterStudy\Lms\Routing\Router;

/** @var Router $router */

$router->group(
	array(
		'middleware' => array(
			\MasterStudy\Lms\Routing\Middleware\Authentication::class,
			\MasterStudy\Lms\Routing\Middleware\Administrator::class,
		),
	),
	function ( Router $router ) {
		$router->get(
			'/migrations/lms',
			\MasterStudy\Lms\MigrationTool\Controllers\ListLMSController::class
		);

		$router->post(
			'/migrations/start',
			\MasterStudy\Lms\MigrationTool\Controllers\StartMigrationController::class
		);

		$router->get(
			'/migrations/active',
			\MasterStudy\Lms\MigrationTool\Controllers\GetActiveMigrationController::class
		);

		// Registered before /migrations/{session_id}: the first matching route wins.
		$router->get(
			'/migrations/preview',
			\MasterStudy\Lms\MigrationTool\Controllers\PreviewMigrationController::class
		);

		$router->get(
			'/migrations/undo',
			\MasterStudy\Lms\MigrationTool\Controllers\GetUndoSummaryController::class
		);

		$router->post(
			'/migrations/undo',
			\MasterStudy\Lms\MigrationTool\Controllers\UndoMigrationController::class
		);

		$router->get(
			'/migrations/{session_id}',
			\MasterStudy\Lms\MigrationTool\Controllers\GetMigrationStatusController::class
		);

		$router->get(
			'/migrations/{session_id}/report/{group}',
			\MasterStudy\Lms\MigrationTool\Controllers\GetMigrationReportController::class
		);

		$router->delete(
			'/migrations/{session_id}',
			\MasterStudy\Lms\MigrationTool\Controllers\CancelMigrationController::class
		);
	}
);
