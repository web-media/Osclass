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
 * Model database for Item table
 *
 * @package    Osclass
 * @subpackage Model
 * @since      unknown
 */
class Item extends DAO
{
    /**
     * It references to self object: Item.
     * It is used as a singleton
     *
     * @access private
     * @since  unknown
     * @var Item
     */
    private static $instance;

    /**
     * Set data related to t_item table
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTableName('t_item');
        $this->setPrimaryKey('pk_i_id');
        $array_fields = array(
            'pk_i_id',
            'fk_i_user_id',
            'fk_i_category_id',
            'dt_pub_date',
            'dt_mod_date',
            'f_price',
            'i_price',
            'fk_c_currency_code',
            's_contact_name',
            's_contact_email',
            's_contact_phone',
            'b_premium',
            's_ip',
            'b_enabled',
            'b_active',
            'b_spam',
            's_secret',
            'b_show_email',
            'dt_expiration'
        );
        $this->setFields($array_fields);
    }

    /**
     * It creates a new Item object class if it has been created
     * before, it return the previous object
     *
     * @access public
     * @return Item
     * @since  unknown
     */
    public static function newInstance()
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * List items ordered by views
     *
     * @access public
     *
     * @param int $limit
     *
     * @return array of items
     * @since  unknown
     */
    public function mostViewed($limit = 10)
    {
        $this->dao->select();
        $this->dao->from($this->getTableName() . ' i, ' . DB_TABLE_PREFIX . 't_item_location l, ' . DB_TABLE_PREFIX
            . 't_item_stats s');
        $this->dao->where('l.fk_i_item_id = i.pk_i_id AND s.fk_i_item_id = i.pk_i_id');
        $this->dao->groupBy('s.fk_i_item_id');
        $this->dao->orderBy('i_num_views', 'DESC');
        $this->dao->limit($limit);

        $result = $this->dao->get();
        if ($result == false) {
            return array();
        }
        $items = $result->result();

        return $this->extendData($items);
    }

    /**
     * Extends the given array $items with description in available locales
     *
     * @access public
     *
     * @param array $items array set of items
     *
     * @return array with description extended with all available locales
     *
     */
    public function extendData($items)
    {
        if (!empty($items)) {
            $prefLocale = OC_ADMIN ? osc_current_admin_locale() : osc_current_user_locale();
            $itemIds    = array_column($items, 'pk_i_id');

            // Set ids
            $this->dao->from($this->getTableName() . ' as i');
            $this->dao->whereIn('i.pk_i_id', $itemIds);
            $this->dao->groupBy('i.pk_i_id');

            // select sum of item_stats

            $this->dao->select('SUM(`s`.`i_num_views`) as `i_num_views`');
            $this->dao->select('SUM(`s`.`i_num_spam`) as `i_num_spam`');
            $this->dao->select('SUM(`s`.`i_num_bad_classified`) as `i_num_bad_classified`');
            $this->dao->select('SUM(`s`.`i_num_repeated`) as `i_num_repeated`');
            $this->dao->select('SUM(`s`.`i_num_offensive`) as `i_num_offensive`');
            $this->dao->select('SUM(`s`.`i_num_expired`) as `i_num_expired` ');
            $this->dao->select('SUM(`s`.`i_num_premium_views`) as `i_num_premium_views` ');
            $this->dao->join(DB_TABLE_PREFIX . 't_item_stats as s', 'i.pk_i_id = s.fk_i_item_id');


            // populate locations

            $this->dao->select('l.*');
            $this->dao->join(DB_TABLE_PREFIX . 't_item_location as l', 'i.pk_i_id = l.fk_i_item_id');


            // populate categories

            $this->dao->select('cd.s_name as s_category_name');
            $this->dao->join(
                DB_TABLE_PREFIX . 't_category_description as cd',
                'i.fk_i_category_id = cd.fk_i_category_id'
            );
            $this->dao->where('cd.fk_c_locale_code', $prefLocale);


            $result      = $this->dao->get();
            $extraFields = $result->result();
            unset($result);


            //get description

            $this->dao->select('d.fk_i_item_id, d.fk_c_locale_code, d.s_title, d.s_description');
            $this->dao->from(DB_TABLE_PREFIX . 't_item_description as d');
            $this->dao->whereIn('d.fk_i_item_id', $itemIds);

            $result       = $this->dao->get();
            $descriptions = $result->result();
            unset($result);


            //Merge all data to given $items array
            foreach ($items as $itemKey => $aItem) {
                $aItem['locale'] = array();
                if (isset($descriptions)) {
                    foreach ($descriptions as $itemDesc) {
                        if ($itemDesc['fk_i_item_id'] === $aItem['pk_i_id']) {
                            if ($itemDesc['s_title'] || $itemDesc['s_description']) {
                                $aItem['locale'][$itemDesc['fk_c_locale_code']] = $itemDesc;
                            }
                            unset($itemDesc);
                        }
                    }

                    if (isset($aItem['locale'][$prefLocale])) {
                        $aItem['s_title']       = $aItem['locale'][$prefLocale]['s_title'];
                        $aItem['s_description'] = $aItem['locale'][$prefLocale]['s_description'];
                    } else {
                        $data                   = current($aItem['locale']);
                        $aItem['s_title']       = $data['s_title'];
                        $aItem['s_description'] = $data['s_description'];
                        unset($data);
                    }
                }


                if (isset($extraFields)) {
                    foreach ($extraFields as $key => $extraField) {
                        if ($aItem['pk_i_id'] === $extraField['fk_i_item_id']) {
                            $items[$itemKey] = array_merge($aItem, $extraField);
                            unset($extraFields[$key]);
                        }
                    }
                }
            }
        }

        return $items;
    }

    /**
     * List Items with category name
     *
     * @access public
     * @return array of items
     * @since  unknown
     */
    public function listAllWithCategories()
    {
        $this->dao->select('i.*, cd.s_name AS s_category_name ');
        $this->dao->from($this->getTableName() . ' i, ' . DB_TABLE_PREFIX . 't_category c, ' . DB_TABLE_PREFIX
            . 't_category_description cd');
        $this->dao->where('c.pk_i_id = i.fk_i_category_id AND cd.fk_i_category_id = i.fk_i_category_id');
        $result = $this->dao->get();
        if ($result == false) {
            return array();
        }

        return $result->result();
    }

    /**
     * Find item resources belong to an item given its id
     *
     * @access public
     *
     * @param int $id Item id
     *
     * @return array of resources
     * @since  unknown
     */
    public function findResourcesByID($id)
    {
        return ItemResource::newInstance()->getResources($id);
    }

    /**
     * Find the item location given a item id
     *
     * @access public
     *
     * @param int $id Item id
     *
     * @return array of location
     * @since  unknown
     */
    public function findLocationByID($id)
    {
        return ItemLocation::newInstance()->findByPrimaryKey($id);
    }

    /**
     * Find items belong to a category given its id
     *
     * @access public
     *
     * @param int $catId
     *
     * @return array of items
     * @since  unknown
     */
    public function findByCategoryID($catId)
    {
        return $this->listWhere('fk_i_category_id = %d', (int)$catId);
    }

    /**
     * Comodin function to serve multiple queries
     *
     * @access public
     * @return array of items
     * @since  3.x.x
     */
    public function listWhere(...$args)
    {
        $sql  = null;
        switch (func_num_args()) {
            case 0:
                return array();
            case 1:
                $sql = $args[0];
                break;
            default:
                $format = array_shift($args);
                foreach ($args as $k => $v) {
                    $args[$k] = $this->dao->escape($v);
                }
                $sql = vsprintf($format, $args);
                break;
        }

        $this->dao->select('l.*, i.*');
        $this->dao->from($this->getTableName() . ' i, ' . DB_TABLE_PREFIX . 't_item_location l');
        $this->dao->where('l.fk_i_item_id = i.pk_i_id');
        $this->dao->where($sql);
        $result = $this->dao->get();
        if ($result == false) {
            return array();
        }
        $items = $result->result();

        return $this->extendData($items);
    }

    /**
     * Find items belong to a phone number
     *
     * @access public
     *
     * @param $phone
     *
     * @return array
     * @since  unknown
     *
     */
    public function findByPhone($phone)
    {
        return $this->listWhere('s_contact_phone = %s', $phone);
    }

    /**
     * Find items belong to an email
     *
     * @access public
     *
     * @param $email
     *
     * @return array
     * @since  unknown
     *
     */
    public function findByEmail($email)
    {
        return $this->listWhere('s_contact_email = %s', $email);
    }

    /**
     * Count all items, or all items belong to a category id, can be filtered
     * by $options  ['ACTIVE|INACTIVE|ENABLED|DISABLED|SPAM|NOTSPAM|EXPIRED|NOTEXPIRED|PREMIUM|TODAY']
     *
     * @access public
     *
     * @param int   $categoryId
     * @param mixed $options could be a string with | separator or an array with the options
     *
     * @return int total items
     * @since  unknown
     */
    public function totalItems($categoryId = null, $options = null)
    {
        $this->dao->select('count(*) as total');
        $this->dao->from($this->getTableName() . ' i');
        if (null !== $categoryId) {
            $this->dao->join(DB_TABLE_PREFIX . 't_category c', 'c.pk_i_id = i.fk_i_category_id');
            $this->dao->where('i.fk_i_category_id', $categoryId);
        }

        $this->addWhereByOptions($options);

        $result = $this->dao->get();
        if ($result == false) {
            return 0;
        }
        $total_ads = $result->row();

        return $total_ads['total'];
    }

    /**
     * Add where condition by options
     * $options  ['ACTIVE|INACTIVE|ENABLED|DISABLED|SPAM|NOTSPAM|EXPIRED|NOTEXPIRED|PREMIUM|TODAY']
     *
     * @access  private
     *
     * @param string | array $options could be a string with | separator or an array with the options
     *
     * @since   4.0.0
     */
    private function addWhereByOptions($options)
    {
        if (!is_array($options)) {
            $options = explode('|', $options);
        }
        foreach ($options as $option) {
            switch ($option) {
                case 'ACTIVE':
                    $this->dao->where('i.b_active', 1);
                    break;
                case 'INACTIVE':
                    $this->dao->where('i.b_active', 0);
                    break;
                case 'ENABLED':
                    $this->dao->where('i.b_enabled', 1);
                    break;
                case 'DISABLED':
                    $this->dao->where('i.b_enabled', 0);
                    break;
                case 'SPAM':
                    $this->dao->where('i.b_spam', 1);
                    break;
                case 'NOTSPAM':
                    $this->dao->where('i.b_spam', 0);
                    break;
                case 'EXPIRED':
                    $this->dao->where('( i.b_premium = 0 && i.dt_expiration < \'' . date('Y-m-d H:i:s') . '\' )');
                    break;
                case 'NOTEXPIRED':
                    $this->dao->where('( i.b_premium = 1 || i.dt_expiration >= \'' . date('Y-m-d H:i:s') . '\' )');
                    break;
                case 'PREMIUM':
                    $this->dao->where('i.b_premium', 1);
                    break;
                case 'TODAY':
                    $this->dao->where('DATEDIFF(\'' . date('Y-m-d H:i:s') . '\', i.dt_pub_date) < 1');
                    break;
                default:
            }
        }
    }

    /**
     * LEAVE THIS FOR COMPATIBILITIES ISSUES (ONLY SITEMAP GENERATOR)
     * BUT REMEMBER TO DELETE IN ANYTHING > 2.1.x THANKS
     *
     * @param      $category
     * @param bool $enabled
     * @param bool $active
     *
     * @return int
     */
    public function numItems($category, $enabled = true, $active = true)
    {
        $this->dao->select('COUNT(*) AS total');
        $this->dao->from($this->getTableName());
        $this->dao->where('fk_i_category_id', (int)$category['pk_i_id']);
        $this->dao->where('b_enabled', $enabled);
        $this->dao->where('b_active', $active);
        $this->dao->where('b_spam', 0);

        $this->dao->where('( b_premium = 1 || dt_expiration >= \'' . date('Y-m-d H:i:s') . '\' )');

        $result = $this->dao->get();

        if ($result == false) {
            return 0;
        }

        if ($result->numRows() == 0) {
            return 0;
        }

        $row = $result->row();

        return $row['total'];
    }

    /**
     * @param int $limit
     *
     * @return array
     */
    public function listLatest($limit = 10)
    {
        return $this->listWhere(' b_active = 1 AND b_enabled = 1 ORDER BY dt_pub_date DESC LIMIT %d', (int)$limit);
    }

    /**
     * Insert title and description for a given locale and item id.
     *
     * @access public
     *
     * @param string $id Item id
     * @param string $locale
     * @param string $title
     * @param string $description
     *
     * @return boolean
     * @since  unknown
     */
    public function insertLocale($id, $locale, $title, $description)
    {
        $array_set = array(
            'fk_i_item_id'     => $id,
            'fk_c_locale_code' => $locale,
            's_title'          => $title,
            's_description'    => $description
        );

        return $this->dao->insert(DB_TABLE_PREFIX . 't_item_description', $array_set);
    }

    /**
     * Find items belong to an user given its id
     *
     * @access public
     *
     * @param int $userId User id
     * @param int $start  begining
     * @param int $end    ending
     *
     * @return array of items
     * @since  unknown
     */
    public function findByUserID($userId, $start = 0, $end = null)
    {
        $condition = "fk_i_user_id = $userId";

        return $this->findItemByTypes($condition, 'all', false, $start, $end);
    }

    /**
     * Find enabled items or count of items by types with given where condition
     *
     * @access public
     *
     * @param string | array $conditions Where condition on t_item table i.e "pk_i_id = 3"
     * @param int            $limit      beginning from $start
     * @param int            $offset     ending
     * @param bool           $itemType   item(active, expired, pending, pending validate, premium, all, enabled,
     *                                   blocked)
     *
     * @return array | int array of items or count of item
     * @since  unknown
     *
     */
    public function findItemByTypes($conditions = null, $itemType = false, $count = false, $limit = 0, $offset = null)
    {
        $this->dao->from($this->getTableName().' i');
        if ($conditions !== null) {
            if (is_array($conditions)) {
                foreach ($conditions as $condition) {
                    $this->dao->where($condition);
                }
            } else {
                $this->dao->where($conditions);
            }
        }

        $this->addWhereByType($itemType);

        if ($count === true) {
            $this->dao->select('count(pk_i_id) as total');
            $result = $this->dao->get();
            if ($result === false) {
                return 0;
            }
            $items = $result->row();

            return $items['total'];
        }

        $this->dao->orderBy('dt_pub_date', 'DESC');

        if ($offset !== null) {
            $this->dao->limit($limit, $offset);
        } elseif ($limit > 0) {
            $this->dao->limit($limit);
        }

        $result = $this->dao->get();
        if ($result === false) {
            return array();
        }
        $items = $result->result();

        return $this->extendData($items);
    }

    /**
     * add conditions by type
     *
     * @param $itemType
     */
    private function addWhereByType($itemType)
    {
        switch ($itemType) {
            case 'blocked':
                $this->addWhereByOptions(['DISABLED']);

                return;
            case 'active':
                $this->addWhereByOptions(['ACTIVE', 'NOTEXPIRED']);

                return;
            case 'nospam':
                $this->addWhereByOptions(['ACTIVE', 'NOSPAM', 'NOTEXPIRED']);

                return;
            case 'expired':
                $this->addWhereByOptions(['EXPIRED']);

                return;
            case 'pending':
            case 'pending_validate':
                $this->addWhereByOptions(['INACTIVE']);

                return;
            case 'premium':
                $this->addWhereByOptions(['PREMIUM']);

                return;
            case 'all':
                return;
            default:
                $this->addWhereByOptions(['ENABLED']);
        }
    }

    /**
     * Count items belong to an user given its id
     *
     * @access public
     *
     * @param int $userId User id
     *
     * @return int number of items
     * @since  unknown
     */
    public function countByUserID($userId)
    {
        return $this->countItemTypesByUserID($userId, 'all');
    }

    /**
     * Count items by User Id according the
     *
     * @access public
     *
     * @param int    $userId   User id
     * @param bool   $itemType (active, expired, pending validate, premium, all, enabled, blocked)
     * @param string $cond
     *
     * @return int number of items
     * @since  unknown
     */
    public function countItemTypesByUserID($userId, $itemType = false, $cond = '')
    {
        $condition[] = "fk_i_user_id = $userId";
        if ($cond) {
            $condition[] = $cond;
        }

        return $this->findItemByTypes($condition, $itemType, true);
    }

    /**
     * Find enabled items belong to an user given its id
     *
     * @access public
     *
     * @param int $userId User id
     * @param int $start  beginning from $start
     * @param int $end    ending
     *
     * @return array of items
     * @since  unknown
     */
    public function findByUserIDEnabled($userId, $start = 0, $end = null)
    {
        $condition = "fk_i_user_id = $userId";

        return $this->findItemByTypes($condition, false, false, $start, $end);
    }

    /**
     * Find enabled items which are going to expired
     *
     * @access public
     *
     * @param int $hours
     *
     * @return array of items
     * @since  3.2
     */
    public function findByHourExpiration($hours = 24)
    {
        $conditions = ['TIMESTAMPDIFF(HOUR, NOW(), dt_expiration) = ' . $hours, 'b_active = 1', 'b_spam = 0'];

        return $this->findItemByTypes($conditions);
    }

    /**
     * Find enabled items which are going to expired
     *
     * @access public
     *
     * @param int $days
     *
     * @return array of items
     * @since  3.2
     */
    public function findByDayExpiration($days = 1)
    {
        $conditions = ['TIMESTAMPDIFF(DAY, NOW(), dt_expiration) = ' . $days, 'b_active = 1', 'b_spam = 0'];

        return $this->findItemByTypes($conditions);
    }

    /**
     * Count enabled items belong to an user given its id
     *
     * @access public
     *
     * @param int $userId User id
     *
     * @return int number of items
     * @since  unknown
     */
    public function countByUserIDEnabled($userId)
    {
        return $this->countItemTypesByUserID($userId, 'enabled');
    }

    /**
     * Find enable items according the
     *
     * @access public
     *
     * @param int  $userId   User id
     * @param int  $start    beginning from $start
     * @param int  $end      ending
     * @param bool $itemType item(active, expired, pending, premium, all, enabled, blocked)
     *
     * @return array of items
     * @since  unknown
     *
     */
    public function findItemTypesByUserID($userId, $start = 0, $end = null, $itemType = false)
    {
        return $this->findItemByTypes("fk_i_user_id = $userId", $itemType, false, $start, $end);
    }

    /**
     * Count items by Email according the
     * Useful for counting item that posted by unregistered user
     *
     * @access public
     *
     * @param int    $email    Email
     * @param bool   $itemType (active, expired, pending validate, premium, all, enabled, blocked)
     * @param string $cond
     *
     * @return int number of items
     * @since  unknown
     */
    public function countItemTypesByEmail($email, $itemType = false, $cond = '')
    {
        $where_email = "s_contact_email = " . $this->dao->escape((string)$email);
        if ($cond) {
            $conditions = array($where_email, $cond);
        } else {
            $conditions = $where_email;
        }

        return $this->findItemByTypes($conditions, $itemType, true);
    }

    /**
     * Clear item stat given item id and stat to clear
     * $stat array('spam', 'duplicated', 'bad', 'offensive', 'expired', 'all')
     *
     * @access public
     *
     * @param int    $id
     * @param string $stat
     *
     * @return mixed int if updated correctly or false when error occurs
     * @since  unknown
     */
    public function clearStat($id, $stat)
    {
        switch ($stat) {
            case 'spam':
                $array_set = array('i_num_spam' => 0);
                break;
            case 'duplicated':
                $array_set = array('i_num_repeated' => 0);
                break;
            case 'bad':
                $array_set = array('i_num_bad_classified' => 0);
                break;
            case 'offensive':
                $array_set = array('i_num_offensive' => 0);
                break;
            case 'expired':
                $array_set = array('i_num_expired' => 0);
                break;
            case 'all':
                $array_set = array(
                    'i_num_spam'           => 0,
                    'i_num_repeated'       => 0,
                    'i_num_bad_classified' => 0,
                    'i_num_offensive'      => 0,
                    'i_num_expired'        => 0
                );
                break;
            default:
                break;
        }
        $array_conditions = array('fk_i_item_id' => $id);

        if (isset($array_set)) {
            return $this->dao->update(DB_TABLE_PREFIX . 't_item_stats', $array_set, $array_conditions);
        }
    }

    /**
     * Update title and description given a item id and locale.
     *
     * @access public
     *
     * @param int    $id
     * @param string $locale
     * @param string $title
     * @param string $text
     *
     * @return bool
     * @since  unknown
     */
    public function updateLocaleForce($id, $locale, $title, $text)
    {
        $array_replace = array(
            's_title'          => $title,
            's_description'    => $text,
            'fk_c_locale_code' => $locale,
            'fk_i_item_id'     => $id
        );

        return $this->dao->replace(DB_TABLE_PREFIX . 't_item_description', $array_replace);
    }

    /**
     * Update dt_expiration field, using $expiration_time
     *
     * @param       $id
     * @param mixed $expiration_time could be interget (number of days) or directly a date
     * @param bool  $do_stats
     *
     * @return string new date expiration, false if error occurs
     *
     */
    public function updateExpirationDate($id, $expiration_time, $do_stats = true)
    {
        if (!$expiration_time) {
            return false;
        }

        $this->dao->select('dt_expiration');
        $this->dao->from($this->getTableName());
        $this->dao->where('pk_i_id', $id);
        $result = $this->dao->get();

        if ($result !== false) {
            $item        = $result->row();
            $expired_old = osc_isExpired($item['dt_expiration']);
            if (ctype_digit($expiration_time)) {
                if ($expiration_time > 0) {
                    $dt_expiration = sprintf(
                        'date_add(%s.dt_pub_date, INTERVAL %d DAY)',
                        $this->getTableName(),
                        $expiration_time
                    );
                } else {
                    $dt_expiration = '9999-12-31 23:59:59';
                }
            } else {
                $dt_expiration = $expiration_time;
            }
            $result = $this->dao->update(
                $this->getTableName(),
                sprintf('dt_expiration = %s', $dt_expiration),
                sprintf(' WHERE pk_i_id = %d', $id)
            );
            if ($result && $result > 0) {
                $this->dao->select('i.dt_expiration, i.fk_i_user_id, i.fk_i_category_id, l.fk_c_country_code');
                $this->dao->select('l.fk_i_region_id, l.fk_i_city_id');
                $this->dao->from($this->getTableName() . ' i, ' . DB_TABLE_PREFIX . 't_item_location l');
                $this->dao->where('i.pk_i_id = l.fk_i_item_id');
                $this->dao->where('i.pk_i_id', $id);
                $result = $this->dao->get();
                $_item  = $result->row();

                if (!$do_stats) {
                    return $_item['dt_expiration'];
                }

                $expired = osc_isExpired($_item['dt_expiration']);
                if ($expired !== $expired_old) {
                    if ($expired) {
                        if ($_item['fk_i_user_id'] != null) {
                            User::newInstance()->decreaseNumItems($_item['fk_i_user_id']);
                        }
                        CategoryStats::newInstance()->decreaseNumItems($_item['fk_i_category_id']);
                        CountryStats::newInstance()->decreaseNumItems($_item['fk_c_country_code']);
                        RegionStats::newInstance()->decreaseNumItems($_item['fk_i_region_id']);
                        CityStats::newInstance()->decreaseNumItems($_item['fk_i_city_id']);
                    } else {
                        if ($_item['fk_i_user_id'] != null) {
                            User::newInstance()->increaseNumItems($_item['fk_i_user_id']);
                        }
                        CategoryStats::newInstance()->increaseNumItems($_item['fk_i_category_id']);
                        CountryStats::newInstance()->increaseNumItems($_item['fk_c_country_code']);
                        RegionStats::newInstance()->increaseNumItems($_item['fk_i_region_id']);
                        CityStats::newInstance()->increaseNumItems($_item['fk_i_city_id']);
                    }
                }

                return $_item['dt_expiration'];
            }
        }

        return false;
    }

    /**
     * Enable all items by given category ids
     *
     * @param int 0|1 $enable
     * @param array $aIds
     *
     * @return \DBRecordsetClass
     */
    public function enableByCategory($enable, $aIds)
    {
        $sql = sprintf('UPDATE %st_item SET b_enabled = %d WHERE ', DB_TABLE_PREFIX, $enable);
        $sql .= sprintf('%st_item.fk_i_category_id IN (%s)', DB_TABLE_PREFIX, implode(',', $aIds));

        return $this->dao->query($sql);
    }

    /**
     * Return the number of items marked as $type
     *
     * @param string $type spam, repeated, bad_classified, offensive, expired
     *
     * @return int
     */
    public function countByMarkas($type)
    {
        $this->dao->select('count(*) as total');
        $this->dao->from($this->getTableName() . ' i');
        $this->dao->from(DB_TABLE_PREFIX . 't_item_stats s');

        $this->dao->where('i.pk_i_id = s.fk_i_item_id');
        // i_num_spam, i_num_repeated, i_num_bad_classified, i_num_offensive, i_num_expired
        if (null !== $type) {
            switch ($type) {
                case 'spam':
                    $this->dao->where('s.i_num_spam > 0 AND i.b_spam = 0');
                    break;
                case 'repeated':
                    $this->dao->where('s.i_num_repeated > 0');
                    break;
                case 'bad_classified':
                    $this->dao->where('s.i_num_bad_classified > 0');
                    break;
                case 'offensive':
                    $this->dao->where('s.i_num_offensive > 0');
                    break;
                case 'expired':
                    $this->dao->where('s.i_num_expired > 0');
                    break;
                default:
            }
        } else {
            return 0;
        }

        $result = $this->dao->get();
        if ($result === false) {
            return 0;
        }
        $total_ads = $result->row();

        return $total_ads['total'];
    }

    /**
     * Return meta fields for a given item
     *
     * @access public
     *
     * @param int $id Item id
     *
     * @return array meta fields array
     * @since  unknown
     */
    public function metaFields($id)
    {
        $this->dao->select('im.s_value as s_value,mf.pk_i_id as pk_i_id, mf.s_name as s_name');
        $this->dao->select('mf.e_type as e_type, im.s_multi as s_multi, mf.s_slug as s_slug');
        $this->dao->from($this->getTableName() . ' i, ' . DB_TABLE_PREFIX . 't_item_meta im, ' . DB_TABLE_PREFIX
            . 't_meta_categories mc, ' . DB_TABLE_PREFIX . 't_meta_fields mf');
        $this->dao->where('mf.pk_i_id = im.fk_i_field_id');
        $this->dao->where('mf.pk_i_id = mc.fk_i_field_id');
        $this->dao->where('mc.fk_i_category_id = i.fk_i_category_id');
        $array_where = array(
            'im.fk_i_item_id' => $id,
            'i.pk_i_id'       => $id
        );
        $this->dao->where($array_where);
        $result = $this->dao->get();
        if ($result == false) {
            return array();
        }
        $aTemp = $result->result();

        $array = array();
        // prepare data - date interval - from <-> to
        foreach ($aTemp as $value) {
            if ($value['e_type'] === 'DATEINTERVAL') {
                $aValue = array();
                if (isset($array[$value['pk_i_id']])) {
                    $aValue = $array[$value['pk_i_id']]['s_value'];
                }
                $aValue[$value['s_multi']] = $value['s_value'];
                $value['s_value']          = $aValue;

                $array[$value['pk_i_id']] = $value;
            } else {
                $array[$value['pk_i_id']] = $value;
            }
        }

        return $array;
    }

    /**
     * Delete by city area
     *
     * @access public
     *
     * @param int $cityAreaId city area id
     *
     * @return bool
     *
     * @since  3.1
     *
     */
    public function deleteByCityArea($cityAreaId)
    {
        $this->dao->select('fk_i_item_id');
        $this->dao->from(DB_TABLE_PREFIX . 't_item_location');
        $this->dao->where('fk_i_city_area_id', $cityAreaId);
        $result = $this->dao->get();
        $items  = $result->result();
        $arows  = 0;
        foreach ($items as $i) {
            $arows += $this->deleteByPrimaryKey($i['fk_i_item_id']);
        }

        return $arows;
    }

    /**
     * Delete by primary key, delete dependencies too
     *
     * @access public
     *
     * @param int $id Item id
     *
     * @return bool
     *
     * @since  unknown
     */
    public function deleteByPrimaryKey($id)
    {
        $item = $this->findByPrimaryKey($id);

        if (null === $item) {
            return false;
        }

        if ($item['b_active'] == 1 && $item['b_enabled'] == 1 && $item['b_spam'] == 0
            && !osc_isExpired($item['dt_expiration'])
        ) {
            if ($item['fk_i_user_id'] != null) {
                User::newInstance()->decreaseNumItems($item['fk_i_user_id']);
            }
            CategoryStats::newInstance()->decreaseNumItems($item['fk_i_category_id']);
            CountryStats::newInstance()->decreaseNumItems($item['fk_c_country_code']);
            RegionStats::newInstance()->decreaseNumItems($item['fk_i_region_id']);
            CityStats::newInstance()->decreaseNumItems($item['fk_i_city_id']);
        }

        ItemActions::deleteResourcesFromHD($id, OC_ADMIN);

        $this->dao->delete(DB_TABLE_PREFIX . 't_item_description', "fk_i_item_id = $id");
        $this->dao->delete(DB_TABLE_PREFIX . 't_item_comment', "fk_i_item_id = $id");
        $this->dao->delete(DB_TABLE_PREFIX . 't_item_resource', "fk_i_item_id = $id");
        $this->dao->delete(DB_TABLE_PREFIX . 't_item_location', "fk_i_item_id = $id");
        $this->dao->delete(DB_TABLE_PREFIX . 't_item_stats', "fk_i_item_id = $id");
        $this->dao->delete(DB_TABLE_PREFIX . 't_item_meta', "fk_i_item_id = $id");

        Plugins::runHook('delete_item', $id);

        return parent::deleteByPrimaryKey($id);
    }

    /**
     * Get the result match of the primary key passed by parameter, extended with
     * location information and number of views.
     *
     * @access public
     *
     * @param int $id Item id
     *
     * @return array|bool
     * @since  unknown
     *
     */
    public function findByPrimaryKey($id)
    {
        if (!is_numeric($id) || $id === null) {
            return array();
        }
        $this->dao->select('i.*');
        $this->dao->from($this->getTableName() . ' i');
        $this->dao->where('i.pk_i_id', $id);
        $result = $this->dao->get();

        if ($result === false) {
            return false;
        }

        if ($result->numRows() === 0) {
            return array();
        }

        $item = $result->row();

        if (null !== $item) {
            return $this->extendDataSingle($item);
        }

        return array();
    }

    /**
     * Extends the given array $item with description in available locales
     *
     * @access public
     *
     * @param array $item
     *
     * @return array item array with description in available locales
     *
     * @since  unknown
     *
     */
    public function extendDataSingle($item)
    {
        return $this->extendData(array($item))[0];
    }

    /**
     * Delete by city
     *
     * @access public
     *
     * @param int $cityId city id
     *
     * @return bool
     *
     * @since  unknown
     */
    public function deleteByCity($cityId)
    {
        $this->dao->select('fk_i_item_id');
        $this->dao->from(DB_TABLE_PREFIX . 't_item_location');
        $this->dao->where('fk_i_city_id', $cityId);
        $result = $this->dao->get();
        $items  = $result->result();
        $arows  = 0;
        foreach ($items as $i) {
            $arows += $this->deleteByPrimaryKey($i['fk_i_item_id']);
        }

        return $arows;
    }

    /**
     * Delete by region
     *
     * @access public
     *
     * @param int $regionId region id
     *
     * @return bool
     *
     * @since  unknown
     */
    public function deleteByRegion($regionId)
    {
        $this->dao->select('fk_i_item_id');
        $this->dao->from(DB_TABLE_PREFIX . 't_item_location');
        $this->dao->where('fk_i_region_id', $regionId);
        $result = $this->dao->get();
        $items  = $result->result();
        $arows  = 0;
        foreach ($items as $i) {
            $arows += $this->deleteByPrimaryKey($i['fk_i_item_id']);
        }

        return $arows;
    }

    /**
     * Delete by country
     *
     * @access public
     *
     * @param int $countryId country id
     *
     * @return bool
     *
     * @since  unknown
     */
    public function deleteByCountry($countryId)
    {
        $this->dao->select('fk_i_item_id');
        $this->dao->from(DB_TABLE_PREFIX . 't_item_location');
        $this->dao->where('fk_c_country_code', $countryId);
        $result = $this->dao->get();
        $items  = $result->result();
        $arows  = 0;
        foreach ($items as $i) {
            $arows += $this->deleteByPrimaryKey($i['fk_i_item_id']);
        }

        return $arows;
    }

    /**
     * Extends the given array $items with category name , and description in available locales
     *
     * @access public
     *
     * @param array $items array with items
     *
     * @return array with category name
     * @since  unknown
     */
    public function extendCategoryName($items)
    {
        $prefLocale = OC_ADMIN ? osc_current_admin_locale() : osc_current_user_locale();

        $results = array();
        foreach ($items as $item) {
            $this->dao->select('fk_c_locale_code, s_name as s_category_name');
            $this->dao->from(DB_TABLE_PREFIX . 't_category_description');
            $this->dao->where('fk_i_category_id', $item['fk_i_category_id']);
            $result       = $this->dao->get();
            $descriptions = $result->result();

            foreach ($descriptions as $desc) {
                $item['locale'][$desc['fk_c_locale_code']]['s_category_name'] = $desc['s_category_name'];
            }
            if (isset($item['locale'][$prefLocale]['s_category_name'])) {
                $item['s_category_name'] = $item['locale'][$prefLocale]['s_category_name'];
            } else {
                $data = current($item['locale']);
                if (isset($data['s_category_name'])) {
                    $item['s_category_name'] = $data['s_category_name'];
                } else {
                    $item['s_category_name'] = '';
                }
                unset($data);
            }
            $results[] = $item;
        }

        return $results;
    }
}

/* file end: ./oc-includes/osclass/model/Item.php */
