<?php
	/**
 * Auto generated file, please don't edit.
 *
 * With: Gobl v1.0.9
 * Time: 1577196384
 */


	namespace OZONE\OZ\Db\Base;

	use Gobl\ORM\ORM;
	use Gobl\ORM\ORMEntityBase;
	use OZONE\OZ\Db\OZAdministratorsQuery as OZAdministratorsQueryReal;

		use OZONE\OZ\Db\OZUsersController as OZUsersControllerRealR;


	/**
	 * Class OZAdmin
	 *
	 * @package OZONE\OZ\Db\Base
	 */
	abstract class OZAdmin extends ORMEntityBase
	{
		const TABLE_NAME = 'oz_administrators';

				const COL_USER_ID = 'admin_user_id';
		const COL_LEVEL = 'admin_level';
		const COL_DATA = 'admin_data';
		const COL_ADD_TIME = 'admin_add_time';
		const COL_VALID = 'admin_valid';

		
		/**
		 * @var \OZONE\OZ\Db\OZUser
		 */
		protected $_r_oz_user;


		/**
		 * OZAdmin constructor.
		 *
		 * @param bool $is_new True for new entity false for entity fetched
		 *                     from the database, default is true.
		 * @param bool $strict Enable/disable strict mode
		 */
		public function __construct($is_new = true, $strict = true)
		{
			parent::__construct(ORM::getDatabase('OZONE\OZ\Db'), $is_new, $strict, OZAdmin::TABLE_NAME, OZAdministratorsQueryReal::class);
		}
		
        /**
         * OneToOne relation between `oz_administrators` and `oz_users`.
         *
         * @return null|\OZONE\OZ\Db\OZUser
         * @throws \Gobl\DBAL\Exceptions\DBALException
         * @throws \Gobl\ORM\Exceptions\ORMException
         * @throws \Gobl\CRUD\Exceptions\CRUDException
         */
        public function getOZUser()
        {
            if (!isset($this->_r_oz_user)) {
                $filters = [];
                if(!is_null($v = $this->getUserId())){
                    $filters['user_id'] = $v;
                }
                if (empty($filters)){
                    return null;
                }

                $m = new OZUsersControllerRealR();
                $this->_r_oz_user = $m->getItem($filters);
            }

            return $this->_r_oz_user;
        }

		
		/**
		 * Getter for column `oz_administrators`.`user_id`.
		 *
		 * @return string the real type is: bigint
		 */
		public function getUserId()
		{
			$column = self::COL_USER_ID;
		    $v = $this->$column;

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_administrators`.`user_id`.
		 *
		 * @param string $user_id
		 *
		 * @return static
		 */
		public function setUserId($user_id)
		{
			$column = self::COL_USER_ID;
			$this->$column = $user_id;

			return $this;
		}

		/**
		 * Getter for column `oz_administrators`.`level`.
		 *
		 * @return int the real type is: int
		 */
		public function getLevel()
		{
			$column = self::COL_LEVEL;
		    $v = $this->$column;

		    if( $v !== null){
		        $v = (int)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_administrators`.`level`.
		 *
		 * @param int $level
		 *
		 * @return static
		 */
		public function setLevel($level)
		{
			$column = self::COL_LEVEL;
			$this->$column = $level;

			return $this;
		}

		/**
		 * Getter for column `oz_administrators`.`data`.
		 *
		 * @return string the real type is: string
		 */
		public function getData()
		{
			$column = self::COL_DATA;
		    $v = $this->$column;

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_administrators`.`data`.
		 *
		 * @param string $data
		 *
		 * @return static
		 */
		public function setData($data)
		{
			$column = self::COL_DATA;
			$this->$column = $data;

			return $this;
		}

		/**
		 * Getter for column `oz_administrators`.`add_time`.
		 *
		 * @return string the real type is: bigint
		 */
		public function getAddTime()
		{
			$column = self::COL_ADD_TIME;
		    $v = $this->$column;

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_administrators`.`add_time`.
		 *
		 * @param string $add_time
		 *
		 * @return static
		 */
		public function setAddTime($add_time)
		{
			$column = self::COL_ADD_TIME;
			$this->$column = $add_time;

			return $this;
		}

		/**
		 * Getter for column `oz_administrators`.`valid`.
		 *
		 * @return bool the real type is: bool
		 */
		public function getValid()
		{
			$column = self::COL_VALID;
		    $v = $this->$column;

		    if( $v !== null){
		        $v = (bool)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_administrators`.`valid`.
		 *
		 * @param bool $valid
		 *
		 * @return static
		 */
		public function setValid($valid)
		{
			$column = self::COL_VALID;
			$this->$column = $valid;

			return $this;
		}

	}