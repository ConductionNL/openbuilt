<?php
// SPDX-License-Identifier: EUPL-1.2

use OCP\Util;

$appId = OCA\OpenBuilt\AppInfo\Application::APP_ID;
Util::addScript($appId, $appId . '-settings');
?>
<div id="openbuilt-settings" data-version="<?php p($_['version'] ?? ''); ?>"></div>
