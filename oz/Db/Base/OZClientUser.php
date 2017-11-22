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
	use OZONE\OZ\Db\OZClientsUsersQuery as OZClientsUsersQueryReal;

	use OZONE\OZ\Db\OZSessionsQuery;
	use OZONE\OZ\Db\OZUsersQuery;
	use OZONE\OZ\Db\OZClientsQuery;


	/**
	 * Class OZClientUser
	 *
	 * @package OZONE\OZ\Db\Base
	 */
	abstract class OZClientUser extends ArrayCapable
	{
		const COL_CLIENT_API_KEY = 'client_api_key';
		const COL_USER_ID = 'user_id';
		const COL_SESSION_ID = 'session_id';
		const COL_TOKEN = 'token';
		const COL_LAST_CHECK = 'last_check';

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
		 * @var \OZONE\OZ\Db\OZSession
		 */
		protected $r_OZ_session;

		/**
		 * @var \OZONE\OZ\Db\OZUser
		 */
		protected $r_OZ_user;

		/**
		 * @var \OZONE\OZ\Db\OZClient
		 */
		protected $r_OZ_client;


		/**
		 * OZClientUser constructor.
		 *
		 * @param bool $is_new True for new entity false for entity fetched
		 *                     from the database, default is true.
		 */
		public function __construct($is_new = true)
		{
			$this->table    = ORM::getDatabase()
								 ->getTable('oz_clients_users');
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
         * OneToOne relation between `oz_clients_users` and `oz_sessions`.
         *
         * @return null|\OZONE\OZ\Db\OZSession
         */
        public function getOZSession()
        {
            if (!isset($this->r_OZ_session)) {
                $m = new OZSessionsQuery();

                $m->filterById($this->getSessionId());

                $this->r_OZ_session = $m->find()->fetchClass();
            }

            return $this->r_OZ_session;
        }

        /**
         * ManyToOne relation between `oz_clients_users` and `oz_users`.
         *
         * @return null|\OZONE\OZ\Db\OZUser
         */
        public function getOZUser()
        {
            if (!isset($this->r_OZ_user)) {
                $m = new OZUsersQuery();

                $m->filterById($this->getUserId());

                $this->r_OZ_user = $m->find()->fetchClass();
            }

            return $this->r_OZ_user;
        }

        /**
         * ManyToOne relation between `oz_clients_users` and `oz_clients`.
         *
         * @return null|\OZONE\OZ\Db\OZClient
         */
        public function getOZClient()
        {
            if (!isset($this->r_OZ_client)) {
                $m = new OZClientsQuery();

                $m->filterByApiKey($this->getClientApiKey());

                $this->r_OZ_client = $m->find()->fetchClass();
            }

            return $this->r_OZ_client;
        }


		/**
		 * Getter for column `oz_clients_users`.`client_api_key`.
		 *
		 * @return string the real type is: string
		 */
		public function getClientApiKey()
		{
		    $v = $this->_getValue(self::COL_CLIENT_API_KEY);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_clients_users`.`client_api_key`.
		 *
		 * @param string $client_api_key
		 *
		 * @return \OZONE\OZ\Db\OZClientUser
		 */
		public function setClientApiKey($client_api_key)
		{
			return $this->_setValue(self::COL_CLIENT_API_KEY, $client_api_key);
		}

		/**
		 * Getter for column `oz_clients_users`.`user_id`.
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
		 * Setter for column `oz_clients_users`.`user_id`.
		 *
		 * @param string $user_id
		 *
		 * @return \OZONE\OZ\Db\OZClientUser
		 */
		public function setUserId($user_id)
		{
			return $this->_setValue(self::COL_USER_ID, $user_id);
		}

		/**
		 * Getter for column `oz_clients_users`.`session_id`.
		 *
		 * @return string the real type is: string
		 */
		public function getSessionId()
		{
		    $v = $this->_getValue(self::COL_SESSION_ID);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_clients_users`.`session_id`.
		 *
		 * @param string $session_id
		 *
		 * @return \OZONE\OZ\Db\OZClientUser
		 */
		public function setSessionId($session_id)
		{
			return $this->_setValue(self::COL_SESSION_ID, $session_id);
		}

		/**
		 * Getter for column `oz_clients_users`.`token`.
		 *
		 * @return string the real type is: string
		 */
		public function getToken()
		{
		    $v = $this->_getValue(self::COL_TOKEN);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_clients_users`.`token`.
		 *
		 * @param string $token
		 *
		 * @return \OZONE\OZ\Db\OZClientUser
		 */
		public function setToken($token)
		{
			return $this->_setValue(self::COL_TOKEN, $token);
		}

		/**
		 * Getter for column `oz_clients_users`.`last_check`.
		 *
		 * @return string the real type is: bigint
		 */
		public function getLastCheck()
		{
		    $v = $this->_getValue(self::COL_LAST_CHECK);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_clients_users`.`last_check`.
		 *
		 * @param string $last_check
		 *
		 * @return \OZONE\OZ\Db\OZClientUser
		 */
		public function setLastCheck($last_check)
		{
			return $this->_setValue(self::COL_LAST_CHECK, $last_check);
		}

		/**
		 * Hydrate this entity with values from an array.
		 *
		 * @param array $row map column name to column value
		 *
		 * @return $this|\OZONE\OZ\Db\OZClientUser
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
				$t       = new OZClientsUsersQueryReal();
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
		 * @return $this|\OZONE\OZ\Db\OZClientUser
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