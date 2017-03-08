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

$userBackend = new \OCA\ServerAuth\ServerAuthBackend(\OC::$server->getSession());
OC_User::useBackend($userBackend);
OC_User::handleApacheAuth();

