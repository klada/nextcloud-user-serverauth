<?php
/**
 * Copyright (c) 2016 klada
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 *
 * User authentication through the web server
 *
 * @category Apps
 * @package  user_serverauth
 * @author   Daniel Klaffenbach <daniel.klaffenbach@***.tu-chemnitz.de>
 * @license  http://www.gnu.org/licenses/agpl AGPL
 */

namespace OCA\ServerAuth;

use OCP\Authentication\IApacheBackend;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDb;
use OCP\UserInterface;
use OCP\IUserBackend;
use OCP\ISession;


class ServerAuthBackend implements IApacheBackend, UserInterface, IUserBackend {
    const BACKEND_NAME        = 'ServerAuthBackend';
    const SERVER_USER_VAR     = 'REMOTE_USER';

    /** @var ISession */
    private $session;

    private $usercache;
    private $datadirectory;

    public function __construct(ISession $session) {
        $this->session = $session;
        $this->usercache = array();
        $config = \OC::$server->getSystemConfig();
        $this->datadirectory = $config->getValue('datadirectory');
    }

//----
//
// Interface methods
//
//----

    public function getBackendName() {
        return self::BACKEND_NAME;
    }

    /**
     * @TODO: Implement SET_DISPLAYNAME
     */
    public function implementsActions($actions) {
        return (bool)(( \OC_User_Backend::CREATE_USER
            | \OC_User_Backend::GET_HOME
            #| \OC_User_Backend::GET_DISPLAYNAME
            | \OC_User_Backend::COUNT_USERS
            ) & $actions);
    }

    /**
     * Checks if an Apache session is active. This is usually the case
     * when $_SERVER["REMOTE_USER"] is set.
     *
     * @TODO: minimum username length is hardcoded.
     */
    public function isSessionActive() {
        \OCP\Util::writeLog(self::BACKEND_NAME, 'isSessionActive()', \OCP\Util::DEBUG);

        if (!isset($_SERVER[self::SERVER_USER_VAR]) ) {
            return false;
        }
        if (strlen($_SERVER[self::SERVER_USER_VAR]) < 2) {
            return false;
        }
        \OCP\Util::writeLog(self::BACKEND_NAME, 'isSessionActive(): active session', \OCP\Util::DEBUG);
        return true;
    }

    public function getDisplayName($uid) {
        $this->loadUser($uid);
        return $this->usercache[$uid]["displayname"];
    }

    public function getDisplayNames($search = '', $limit = null, $offset = null) {
        \OCP\Util::writeLog(self::BACKEND_NAME, 'getDisplayNames()', \OCP\Util::DEBUG);
        if (strlen($search) > 0) {
            $sql = 'SELECT `uid`, `displayname` FROM `*PREFIX*users_serverauth` WHERE (LOWER(`uid`) LIKE LOWER(?) OR LOWER(`displayname`) LIKE LOWER(?))';
            $args = array("%".$search."%", $search."%");
        } else {
            $sql = 'SELECT `uid`, `displayname`  FROM `*PREFIX*users_serverauth`';
            $args = array();
        }
        $result = \OC_DB::executeAudited(
            array(
                'sql' => $sql,
                'limit' => $limit,
                'offset' => $offset
            ),
            $args
        );
        $users = array();
        while ($row = $result->fetchRow()) {
            $users[$row['uid']] = $row['displayname'];
        }
        return $users;
    }
 

    public function getLogoutAttribute() {
        return;
    }

    /**
     * This method is only called within an active Apache session
     * (isSessionActive) and returns the user id.
     */
    public function getCurrentUserId() {
        \OCP\Util::writeLog(self::BACKEND_NAME, 'getCurrentUserId()', \OCP\Util::DEBUG);
        if (!$this->isSessionActive()) {
            return;
        }

        $user = $_SERVER[self::SERVER_USER_VAR];
        if (!$this->userExists($user)) {
            \OC_Hook::emit('OC_User', 'pre_createUser', array('run' => true, 'uid' => $user, 'password' => ""));
            $this->storeUser($user);
            \OC_Hook::emit('OC_User', 'post_createUser', array('uid' => $user, 'password' => ""));
            return $user;
        }
        // required for tokens
        $this->session->set('last-password-confirm', time());
        return $user;
    }

//----
//
// Backend methods (from OC_User_Backend)
//
//----
    public function countUsers() {
        \OCP\Util::writeLog(self::BACKEND_NAME, 'countUsers()', \OCP\Util::DEBUG);
        $query = \OC_DB::prepare('SELECT COUNT(*) FROM `*PREFIX*users_serverauth`');
        $result = $query->execute();
        return $result->fetchOne();
    }

    /**
     * Removes the user from the database and the configured cache.
     */
    public function deleteUser($uid) {
        \OCP\Util::writeLog(self::BACKEND_NAME, 'deleteUser()', \OCP\Util::INFO);
        \OC_DB::executeAudited(
            'DELETE FROM `*PREFIX*users_serverauth` WHERE `uid` = ?', array($uid)
        );
        if (array_key_exists($uid, $this->usercache)) {
            unset($this->usercache[$uid]);
        }
        return true;
    }

    public function getHome($uid) {
        $this->loadUser($uid);
        return $this->usercache[$uid]['home']; 
    }

    /**
     * Returns an array of UIDs known to this backend.
     **/
    public function getUsers($search = '', $limit = 10, $offset = 0) {
        \OCP\Util::writeLog(self::BACKEND_NAME, 'getUsers()', \OCP\Util::DEBUG);
        if (strlen($search) > 0) {
            $sql = 'SELECT `uid` FROM `*PREFIX*users_serverauth` WHERE (LOWER(`uid`) LIKE LOWER(?) OR LOWER(`displayname`) LIKE LOWER(?))';
            $args = array("%".$search."%", $search."%");
        } else {
            $sql = 'SELECT `uid` FROM `*PREFIX*users_serverauth`';
            $args = array();
        }
        $result = \OC_DB::executeAudited(
            array(
                'sql' => $sql,
                'limit' => $limit,
                'offset' => $offset
            ),
            $args
        );
        $users = array();
        while ($row = $result->fetchRow()) {
            $users[] = $row['uid'];
        }
        return $users;
    }


    /**
     * @return bool
     */
    public function hasUserListings() {
        return true;
    }

    public function isEnabled($uid) {
        \OCP\Util::writeLog(self::BACKEND_NAME, 'isEnabled('.$uid.')', \OCP\Util::DEBUG);
        $this->loadUser($uid);
        return (bool) $this->usercache[$uid]['enabled'];
    }

    public function userExists($uid) {
        \OCP\Util::writeLog(self::BACKEND_NAME, 'userExists('.$uid.')', \OCP\Util::DEBUG);
        return $this->loadUser($uid);
    }


//----
//
// Custom methods (only used internally by this backend).
//
//----

    /**
     * Loads the user from the database to the instance cache.
     *
     * @TODO: Use memcache instead.
     **/
    private function loadUser($uid) {
        \OCP\Util::writeLog(self::BACKEND_NAME, 'loadUser()', \OCP\Util::DEBUG);

        if (!array_key_exists($uid, $this->usercache)) {
            $result = \OC_DB::executeAudited(
                'SELECT `home`, `displayname`, `enabled` FROM `*PREFIX*users_serverauth` WHERE LOWER(`uid`) = LOWER(?)',
                array($uid)
            );
            $row = $result->fetchRow();
            if (!$row) {
                return false;
            }
            $this->usercache[$uid] = $row;
        }
        return true;
    }

    /**
     * Adds the user to the database.
     */
    protected function storeUser($uid) {
        \OCP\Util::writeLog(self::BACKEND_NAME, 'storeUser()', \OCP\Util::DEBUG);

        $home = $this->datadirectory.'/'.$uid;

        \OC_DB::executeAudited(
            'INSERT INTO `*PREFIX*users_serverauth` (`uid`, `home`, `displayname`, `enabled`) VALUES (?, ?, ?, ?)',
            array($uid, $home, $uid, 1)
        );
        $this->usercache['uid'] = array('home'=>$home, 'displayname'=>$uid, 'enabled' => 1);

        //Prepare file system
        \OC_Util::setupFS($uid);
        //Force creation of user folder for new users
        \OC::$server->getUserFolder($uid);
    }
}
