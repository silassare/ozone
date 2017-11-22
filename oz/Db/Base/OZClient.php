<?php
	/**
	 * Auto generated file, please don't edit.
	 *
	 * With: Gobl v1.0.0
	 * Time: 1511267802
	 */

	namespace OZONE\OZ\Db\Base;

	use Gobl\DBAL\QueryBuilder;
	use Gobl\ORM\ArrayCapable;
	use Gobl\ORM\Exceptions\ORMException;
	use Gobl\ORM\ORM;
	use OZONE\OZ\Db\OZClientsQuery as OZClientsQueryReal;

	use OZONE\OZ\Db\OZUsersQuery;
	use OZONE\OZ\Db\OZClientsUsersQuery;


	/**
	 * Class OZClient
	 *
	 * @package OZONE\OZ\Db\Base
	 */
	abstract class OZClient extends ArrayCapable
	{
		const COL_API_KEY = 'client_api_key';
		const COL_USER_ID = 'client_user_id';
		const COL_URL = 'client_url';
		const COL_SESSION_LIFE_TIME = 'client_session_life_time';
		const COL_ABOUT = 'client_about';
		const COL_CREATE_TIME = 'client_create_time';
		const COL_VALID = 'client_valid';

		/** @var \Gobl\DBAL\Table */
		protected $table;

		/** @var  array */
		protected $row;

		/** @var  array */
		protected $row_saved;

		/**
		 * @var bool
		 */
		protected $is_new = true;

		/**
		 * @var bool
		 */
		protected $is_saved = false;

		/**
		 * The auto_increment column full name.
		 *
		 * @var string
		 */
		protected $auto_increment_column = null;


		/**
		 * @var \OZONE\OZ\Db\OZUser
		 */
		protected $r_OZ_client_owner;


		/**
		 * OZClient constructor.
		 *
		 * @param bool $is_new True for new entity false for entity fetched
		 *                     from the database, default is true.
		 */
		public function __construct($is_new = true)
		{
			$this->table    = ORM::getDatabase()
								 ->getTable('oz_clients');
			$columns        = $this->table->getColumns();
			$this->is_new   = (bool)$is_new;
			$this->is_saved = !$this->is_new;

			// we initialise row with default value
			foreach ($columns as $column) {
				$full_name             = $column->getFullName();
				$this->row[$full_name] = $column->getDefaultValue();

				// the auto_increment column
				if ($column->isAutoIncrement()) {
					$this->auto_increment_column = $full_name;
				}
			}
		}

        /**
         * OneToOne relation between `oz_clients` and `oz_users`.
         *
         * @return null|\OZONE\OZ\Db\OZUser
         */
        public function getOZClientOwner()
        {
            if (!isset($this->r_OZ_client_owner)) {
                $m = new OZUsersQuery();

                $m->filterById($this->getUserId());

                $this->r_OZ_client_owner = $m->find()->fetchClass();
            }

            return $this->r_OZ_client_owner;
        }

        /**
         * OneToMany relation between `oz_clients` and `oz_clients_users`.
         *
		 * @param null|int $max    maximum row to retrieve
		 * @param int      $offset first row offset
		 *
         * @return \OZONE\OZ\Db\OZClientUser[]
         */
        public function getOZCurrentUsers($max = null, $offset = 0)
        {
            $m = new OZClientsUsersQuery();

            $m->filterByClientApiKey($this->getApiKey());

            return $m->find($max, $offset)->fetchAllClass();
        }


		/**
		 * Getter for column `oz_clients`.`api_key`.
		 *
		 * @return string the real type is: string
		 */
		public function getApiKey()
		{
		    $v = $this->_getValue(self::COL_API_KEY);

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
		 * @return \OZONE\OZ\Db\OZClient
		 */
		public function setApiKey($api_key)
		{
			return $this->_setValue(self::COL_API_KEY, $api_key);
		}

		/**
		 * Getter for column `oz_clients`.`user_id`.
		 *
		 * @return string the real type is: bigint
		 */
		public function getUserId()
		{
		    $v = $this->_getValue(self::COL_USER_ID);

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
		 * @return \OZONE\OZ\Db\OZClient
		 */
		public function setUserId($user_id)
		{
			return $this->_setValue(self::COL_USER_ID, $user_id);
		}

		/**
		 * Getter for column `oz_clients`.`url`.
		 *
		 * @return string the real type is: string
		 */
		public function getUrl()
		{
		    $v = $this->_getValue(self::COL_URL);

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
		 * @return \OZONE\OZ\Db\OZClient
		 */
		public function setUrl($url)
		{
			return $this->_setValue(self::COL_URL, $url);
		}

		/**
		 * Getter for column `oz_clients`.`session_life_time`.
		 *
		 * @return string the real type is: bigint
		 */
		public function getSessionLifeTime()
		{
		    $v = $this->_getValue(self::COL_SESSION_LIFE_TIME);

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
		 * @return \OZONE\OZ\Db\OZClient
		 */
		public function setSessionLifeTime($session_life_time)
		{
			return $this->_setValue(self::COL_SESSION_LIFE_TIME, $session_life_time);
		}

		/**
		 * Getter for column `oz_clients`.`about`.
		 *
		 * @return string the real type is: string
		 */
		public function getAbout()
		{
		    $v = $this->_getValue(self::COL_ABOUT);

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
		 * @return \OZONE\OZ\Db\OZClient
		 */
		public function setAbout($about)
		{
			return $this->_setValue(self::COL_ABOUT, $about);
		}

		/**
		 * Getter for column `oz_clients`.`create_time`.
		 *
		 * @return string the real type is: bigint
		 */
		public function getCreateTime()
		{
		    $v = $this->_getValue(self::COL_CREATE_TIME);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_clients`.`create_time`.
		 *
		 * @param string $create_time
		 *
		 * @return \OZONE\OZ\Db\OZClient
		 */
		public function setCreateTime($create_time)
		{
			return $this->_setValue(self::COL_CREATE_TIME, $create_time);
		}

		/**
		 * Getter for column `oz_clients`.`valid`.
		 *
		 * @return bool the real type is: bool
		 */
		public function getValid()
		{
		    $v = $this->_getValue(self::COL_VALID);

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
		 * @return \OZONE\OZ\Db\OZClient
		 */
		public function setValid($valid)
		{
			return $this->_setValue(self::COL_VALID, $valid);
		}

		/**
		 * Hydrate this entity with values from an array.
		 *
		 * @param array $row map column name to column value
		 *
		 * @return $this|\OZONE\OZ\Db\OZClient
		 */
		public function hydrate(array $row)
		{
			foreach ($row as $column_name => $value) {
				$this->_setValue($column_name, $value);
			}

			return $this;
		}

		/**
		 * To check if this entity is new
		 *
		 * @return bool
		 */
		public function isNew()
		{
			return $this->is_new;
		}

		/**
		 * To check if this entity is saved
		 *
		 * @return bool
		 */
		public function isSaved()
		{
			return $this->is_saved;
		}

		/**
		 * Saves modifications to database.
		 *
		 * @return int|string int for affected row count on update, string for last insert id
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function save()
		{
			if ($this->isNew()) {
				// add
				$ai_column = $this->auto_increment_column;

				if (!empty($ai_column)) {
					$ai_column_value = $this->row[$ai_column];

					if (!is_null($ai_column_value)) {
						throw new ORMException(sprintf('Auto increment column "%s" should be set to null.', $ai_column));
					}
				}

				$columns = array_keys($this->row);
				$values  = array_values($this->row);
				$qb      = new QueryBuilder(ORM::getDatabase());
				$qb->insert()
				   ->into($this->table->getFullName(), $columns)
				   ->values($values);

				$result = $qb->execute();

				if (!empty($ai_column)) {
					if (is_string($result)) {
						$this->row[$ai_column] = $result;
						$returns               = $result; // last insert id
					} else {
						throw new ORMException(sprintf('Unable to get last insert id for column "%s" in table "%s"', $ai_column, $this->table->getName()));
					}
				} else {
					$returns = intval($result); // one row saved
				}
			} elseif (!$this->isSaved() AND isset($this->row_saved)) {
				// update
				$t       = new OZClientsQueryReal();
				$returns = $t->safeUpdate($this->row_saved, $this->row)
							 ->execute();
			} else {
				// nothing to do
				$returns = 0;
			}

			$this->row_saved = $this->row;
			$this->is_new    = false;
			$this->is_saved  = true;

			return $returns;
		}

		/**
		 * Gets a column value.
		 *
		 * @param string $name the column name or full name.
		 *
		 * @return mixed
		 */
		protected function _getValue($name)
		{
			if ($this->table->hasColumn($name)) {
				$column    = $this->table->getColumn($name);
				$full_name = $column->getFullName();

				return $this->row[$full_name];
			}

			return null;
		}

		/**
		 * Sets a column value.
		 *
		 * @param string $name  the column name or full name.
		 * @param mixed  $value the column new value.
		 *
		 * @return $this|\OZONE\OZ\Db\OZClient
		 */
		protected function _setValue($name, $value)
		{
			if ($this->table->hasColumn($name)) {
				$column    = $this->table->getColumn($name);
				$value     = $column->getTypeObject()
									->validate($value);
				$full_name = $column->getFullName();
				if ($this->row[$full_name] !== $value) {
					$this->row[$full_name] = $value;
					$this->is_saved        = false;
				}
			}

			return $this;
		}

		/**
		 * Magic setter for row fetched as class.
		 *
		 * @param string $full_name the column full name
		 * @param mixed  $value     the column value
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		final public function __set($full_name, $value)
		{
			if ($this->isNew()) {
				throw new ORMException(sprintf('You should not try to manually set properties on "%s" use appropriate getters and setters.', get_class($this)));
			}

			if ($this->table->hasColumn($full_name)) {
				$this->row[$full_name]       = $value;
				$this->row_saved[$full_name] = $value;
				$this->is_saved              = true;
			} else {
				throw new ORMException(sprintf('Could not set column "%s", not defined in table "%s".', $full_name, $this->table->getName()));
			}
		}

		/**
		 * {@inheritdoc}
		 */
		public function asArray()
		{
			return $this->row;
		}
	}