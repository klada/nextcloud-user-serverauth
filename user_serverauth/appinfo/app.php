<?php
/**
 * NextCloud - user_serverauth
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Daniel Klaffenbach <daniel.klaffenbach@***.tu-chemnitz.de>
 */
require_once OC_App::getAppPath('user_serverauth').'/user_serverauth.php';

// This is a hack to work around NextCloud issue #3771.
// It is not clear to me why this particular class is not autoloaded by the class loader.
require_once OC::$SERVERROOT."/lib/private/Memcache/ArrayCache.php";

$userBackend = new \OCA\ServerAuth\ServerAuthBackend(\OC::$server->getSession());
OC_User::useBackend($userBackend);
OC_User::handleApacheAuth();

