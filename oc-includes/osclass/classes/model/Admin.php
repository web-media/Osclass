<?php

/*
 *  Copyright 2020 Osclass
 *  Maintained and supported by Mindstellar Community
 *  https://github.com/mindstellar/Osclass
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Model database for Admin table
 *
 * @package    Osclass
 * @subpackage Model
 * @since      unknown
 */
class Admin extends DAO
{
    /**
     * It references to self object: Admin.
     * It is used as a singleton
     *
     * @access private
     * @since  unknown
     * @var Admin
     */
    private static $instance;

    /**
     * array for save currencies
     *
     * @var array
     */
    private $cachedAdmin;

    /**
     * Set data from t_admin table
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTableName('t_admin');
        $this->setPrimaryKey('pk_i_id');

        $return = $this->dao->query('SHOW COLUMNS FROM ' . $this->getTableName() . ' where Field = "b_moderator" ');
        if ($return instanceof DBRecordsetClass) {
            if ($return->numRows() > 0) {
                $this->setFields(array(
                    'pk_i_id',
                    's_name',
                    's_username',
                    's_password',
                    's_email',
                    's_secret',
                    'b_moderator'
                ));
            } else {
                $this->setFields(array('pk_i_id', 's_name', 's_username', 's_password', 's_email', 's_secret'));
            }
        } else {
            throw new mysqli_sql_exception($this->dao->errorDesc);
        }
    }

    /**
     * @return \Admin
     */
    public static function newInstance()
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * @param string $id
     * @param null   $locale
     *
     * @return mixed|string
     */
    public function findByPrimaryKey($id, $locale = null)
    {
        if ($id == '') {
            return '';
        }
        if (isset($this->cachedAdmin[$id])) {
            return $this->cachedAdmin[$id];
        }
        $this->cachedAdmin[$id] = parent::findByPrimaryKey($id);

        return $this->cachedAdmin[$id];
    }

    /**
     * Searches for admin information, given an email address.
     * If email not exist return false.
     *
     * @access public
     *
     * @param string $email
     *
     * @return array|bool
     * @since  unknown
     *
     */
    public function findByEmail($email)
    {
        $this->dao->select();
        $this->dao->from($this->getTableName());
        $this->dao->where('s_email', $email);
        $result = $this->dao->get();

        if ($result->numRows == 0) {
            return false;
        }

        return $result->row();
    }

    /**
     * Searches for admin information, given a username and password
     * If credential don't match return false.
     *
     * @access public
     *
     * @param string $userName
     * @param string $password
     *
     * @return array|bool
     * @since  unknown
     *
     */
    public function findByCredentials($userName, $password)
    {
        $user = $this->findByUsername($userName);
        if ($user !== false && isset($user['s_password']) && osc_verify_password($password, $user['s_password'])) {
            return $user;
        }

        return false;
    }

    /**
     * Searches for admin information, given a username.
     * If admin not exist return false.
     *
     * @access public
     *
     * @param string $username
     *
     * @return array|bool
     * @since  unknown
     *
     */
    public function findByUsername($username)
    {
        $this->dao->select();
        $this->dao->from($this->getTableName());
        $this->dao->where('s_username', $username);
        $result = $this->dao->get();

        if ($result->numRows == 0) {
            return false;
        }

        return $result->row();
    }

    /**
     * Searches for admin information, given a admin id and secret.
     * If credential don't match return false.
     *
     * @access public
     *
     * @param integer $id
     * @param string  $secret
     *
     * @return array|bool
     * @since  unknown
     *
     */
    public function findByIdSecret($id, $secret)
    {
        $this->dao->select();
        $this->dao->from($this->getTableName());
        $conditions = array(
            'pk_i_id'  => $id,
            's_secret' => $secret
        );
        $this->dao->where($conditions);
        $result = $this->dao->get();

        if ($result->numRows == 0) {
            return false;
        }

        return $result->row();
    }

    /**
     * Searches for admin information, given a admin id and password.
     * If credential don't match return false.
     *
     * @access public
     *
     * @param integer $id
     * @param string  $password
     *
     * @return array|bool
     * @since  unknown
     *
     */
    public function findByIdPassword($id, $password)
    {
        $this->dao->select();
        $this->dao->from($this->getTableName());
        $conditions = array(
            'pk_i_id'    => $id,
            's_password' => $password
        );
        $this->dao->where($conditions);
        $result = $this->dao->get();

        if ($result->numRows == 0) {
            return false;
        }

        return $result->row();
    }

    /**
     * Perform a batch delete (for more than one admin ID)
     *
     * @access public
     *
     * @param array $id
     *
     * @return boolean
     * @since  2.3.4
     */
    public function deleteBatch($id)
    {
        $this->dao->from($this->getTableName());
        $this->dao->whereIn('pk_i_id', $id);

        return $this->dao->delete();
    }
}

/* file end: ./oc-includes/osclass/model/Admin.php */
