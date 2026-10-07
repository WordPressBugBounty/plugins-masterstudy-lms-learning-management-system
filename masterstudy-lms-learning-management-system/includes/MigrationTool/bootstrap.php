<?php

defined( 'ABSPATH' ) || exit;

use MasterStudy\Lms\MigrationTool\AdminNotice;
use MasterStudy\Lms\MigrationTool\Jobs\MigrationProcessJob;
use MasterStudy\Lms\MigrationTool\SettingsPage;

( new MigrationProcessJob() )->register();
( new SettingsPage() )->register();
( new AdminNotice() )->register();
