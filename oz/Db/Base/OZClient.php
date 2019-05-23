<?php
	/**
 * Auto generated file, please don't edit.
 *
 * With: Gobl v1.0.0
 * Time: 1557147498
 */

	namespace OZONE\OZ\Db\Base;

	use Gobl\ORM\ORM;
	use Gobl\ORM\ORMEntityBase;
	use OZONE\OZ\Db\OZClientsQuery as OZClientsQueryReal;

		use OZONE\OZ\Db\OZUsersController as OZUsersControllerRealR;


	/**
	 * Class OZClient
	 *
	 * @package OZONE\OZ\Db\Base
	 */
	abstract class OZClient extends ORMEntityBase
	{
		const TABLE_NAME = 'oz_clients';

				const COL_API_KEY = 'client_api_key';
		const COL_USER_ID = 'client_user_id';
		const COL_URL = 'client_url';
		const COL_SESSION_LIFE_TIME = 'client_session_life_time';
		const COL_ABOUT = 'client_about';
		const COL_DATA = 'client_data';
		const COL_ADD_TIME = 'client_add_time';
		const COL_VALID = 'client_valid';

		
		/**
		 * @var \OZONE\OZ\Db\OZUser
		 */
		protected $_r_oz_client_owner;


		/**
		 * OZClient constructor.
		 *
		 * @param bool $is_new True for new entity false for entity fetched
		 *                     from the database, default is true.
		 * @param bool $strict Enable/disable strict mode
		 */
		public function __construct($is_new = true, $strict = true)
		{
			parent::__construct(ORM::getDatabase('OZONE\OZ\Db'), $is_new, $strict, OZClient::TABLE_NAME, OZClientsQueryReal::class);
		}
		
        /**
         * OneToOne relation between `oz_clients` and `oz_users`.
         *
         * @return null|\OZONE\OZ\Db\OZUser
         * @throws \Gobl\DBAL\Exceptions\DBALException
         * @throws \Gobl\ORM\Exceptions\ORMException
         * @throws \Gobl\CRUD\Exceptions\CRUDException
         */
        public function getOZClientOwner()
        {
            if (!isset($this->_r_oz_client_owner)) {
                $filters = [];
                if(!is_null($v = $this->getUserId())){
                    $filters['user_id'] = $v;
                }
                if (empty($filters)){
                    return null;
                }

                $m = new OZUsersControllerRealR();
                $this->_r_oz_client_owner = $m->getItem($filters);
            }

            return $this->_r_oz_client_owner;
        }

		
		/**
		 * Getter for column `oz_clients`.`api_key`.
		 *
		 * @return string the real type is: string
		 */
		public function getApiKey()
		{
			$column = self::COL_API_KEY;
		    $v = $this->$column;

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_clients`.`api_key`.
		 *
		 * @param string $api_key
		 *
		 * @return static
		 */
		public function setApiKey($api_key)
		{
			$column = self::COL_API_KEY;
			$this->$column = $api_key;

			return $this;
		}

		/**
		 * Getter for column `oz_clients`.`user_id`.
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
		 * Setter for column `oz_clients`.`user_id`.
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
		 * Getter for column `oz_clients`.`url`.
		 *
		 * @return string the real type is: string
		 */
		public function getUrl()
		{
			$column = self::COL_URL;
		    $v = $this->$column;

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_clients`.`url`.
		 *
		 * @param string $url
		 *
		 * @return static
		 */
		public function setUrl($url)
		{
			$column = self::COL_URL;
			$this->$column = $url;

			return $this;
		}

		/**
		 * Getter for column `oz_clients`.`session_life_time`.
		 *
		 * @return string the real type is: bigint
		 */
		public function getSessionLifeTime()
		{
			$column = self::COL_SESSION_LIFE_TIME;
		    $v = $this->$column;

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_clients`.`session_life_time`.
		 *
		 * @param string $session_life_time
		 *
		 * @return static
		 */
		public function setSessionLifeTime($session_life_time)
		{
			$column = self::COL_SESSION_LIFE_TIME;
			$this->$column = $session_life_time;

			return $this;
		}

		/**
		 * Getter for column `oz_clients`.`about`.
		 *
		 * @return string the real type is: string
		 */
		public function getAbout()
		{
			$column = self::COL_ABOUT;
		    $v = $this->$column;

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_clients`.`about`.
		 *
		 * @param string $about
		 *
		 * @return static
		 */
		public function setAbout($about)
		{
			$column = self::COL_ABOUT;
			$this->$column = $about;

			return $this;
		}

		/**
		 * Getter for column `oz_clients`.`data`.
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
		 * Setter for column `oz_clients`.`data`.
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
		 * Getter for column `oz_clients`.`add_time`.
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
		 * Setter for column `oz_clients`.`add_time`.
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
		 * Getter for column `oz_clients`.`valid`.
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
		 * Setter for column `oz_clients`.`valid`.
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