<?php
	/**
	 * Auto generated file, please don't edit.
	 *
	 * With: Gobl v1.0.0
	 * Time: 1509638392
	 */

	namespace OZONE\OZ\Db\Base;

	use Gobl\DBAL\QueryBuilder;
	use Gobl\ORM\ArrayCapable;
	use Gobl\ORM\Exceptions\ORMException;
	use Gobl\ORM\ORM;
	use OZONE\OZ\Db\OZAuthenticatorQuery as OZAuthenticatorQueryReal;



	/**
	 * Class OZAuth
	 *
	 * @package OZONE\OZ\Db\Base
	 */
	abstract class OZAuth extends ArrayCapable
	{
		const COL_LABEL = 'auth_label';
		const COL_FOR = 'auth_for';
		const COL_CODE = 'auth_code';
		const COL_TOKEN = 'auth_token';
		const COL_TRY_MAX = 'auth_try_max';
		const COL_TRY_COUNT = 'auth_try_count';
		const COL_EXPIRE = 'auth_expire';

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
		protected $saved = false;

		/**
		 * @var bool
		 */
		protected $modified = false;

		/**
		 * The auto_increment column full name.
		 *
		 * @var string
		 */
		protected $auto_increment_column = null;



		/**
		 * OZAuth constructor.
		 *
		 * @param bool $is_new True for new entity false for entity fetched
		 *                     from the database, default is true.
		 */
		public function __construct($is_new = true)
		{
			$this->table  = ORM::getDatabase()
							   ->getTable('oz_authenticator');
			$columns      = $this->table->getColumns();
			$this->is_new = (bool)$is_new;

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
		 * Getter for column `oz_authenticator`.`label`.
		 *
		 * @return string the real type is: string
		 */
		public function getLabel()
		{
		    $v = $this->_getValue(self::COL_LABEL);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_authenticator`.`label`.
		 *
		 * @param string $label
		 *
		 * @return \OZONE\OZ\Db\OZAuth
		 */
		public function setLabel($label)
		{
			return $this->_setValue(self::COL_LABEL, $label);
		}

		/**
		 * Getter for column `oz_authenticator`.`for`.
		 *
		 * @return string the real type is: string
		 */
		public function getFor()
		{
		    $v = $this->_getValue(self::COL_FOR);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_authenticator`.`for`.
		 *
		 * @param string $for
		 *
		 * @return \OZONE\OZ\Db\OZAuth
		 */
		public function setFor($for)
		{
			return $this->_setValue(self::COL_FOR, $for);
		}

		/**
		 * Getter for column `oz_authenticator`.`code`.
		 *
		 * @return string the real type is: string
		 */
		public function getCode()
		{
		    $v = $this->_getValue(self::COL_CODE);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_authenticator`.`code`.
		 *
		 * @param string $code
		 *
		 * @return \OZONE\OZ\Db\OZAuth
		 */
		public function setCode($code)
		{
			return $this->_setValue(self::COL_CODE, $code);
		}

		/**
		 * Getter for column `oz_authenticator`.`token`.
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
		 * Setter for column `oz_authenticator`.`token`.
		 *
		 * @param string $token
		 *
		 * @return \OZONE\OZ\Db\OZAuth
		 */
		public function setToken($token)
		{
			return $this->_setValue(self::COL_TOKEN, $token);
		}

		/**
		 * Getter for column `oz_authenticator`.`try_max`.
		 *
		 * @return int the real type is: int
		 */
		public function getTryMax()
		{
		    $v = $this->_getValue(self::COL_TRY_MAX);

		    if( $v !== null){
		        $v = (int)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_authenticator`.`try_max`.
		 *
		 * @param int $try_max
		 *
		 * @return \OZONE\OZ\Db\OZAuth
		 */
		public function setTryMax($try_max)
		{
			return $this->_setValue(self::COL_TRY_MAX, $try_max);
		}

		/**
		 * Getter for column `oz_authenticator`.`try_count`.
		 *
		 * @return int the real type is: int
		 */
		public function getTryCount()
		{
		    $v = $this->_getValue(self::COL_TRY_COUNT);

		    if( $v !== null){
		        $v = (int)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_authenticator`.`try_count`.
		 *
		 * @param int $try_count
		 *
		 * @return \OZONE\OZ\Db\OZAuth
		 */
		public function setTryCount($try_count)
		{
			return $this->_setValue(self::COL_TRY_COUNT, $try_count);
		}

		/**
		 * Getter for column `oz_authenticator`.`expire`.
		 *
		 * @return string the real type is: bigint
		 */
		public function getExpire()
		{
		    $v = $this->_getValue(self::COL_EXPIRE);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_authenticator`.`expire`.
		 *
		 * @param string $expire
		 *
		 * @return \OZONE\OZ\Db\OZAuth
		 */
		public function setExpire($expire)
		{
			return $this->_setValue(self::COL_EXPIRE, $expire);
		}

		/**
		 * Hydrate, load, populate this entity with values from an array.
		 *
		 * @param array $row map column name to column value
		 *
		 * @return $this|\OZONE\OZ\Db\OZAuth
		 */
		public function hydrate(array $row)
		{
			foreach ($row as $column_name => $value) {
				$this->_setValue($column_name, $value);
			}

			return $this;
		}

		/**
		 * To check if this entity is modified
		 *
		 * @return bool
		 */
		public function isModified()
		{
			return $this->modified;
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
			return $this->saved;
		}

		/**
		 * Saves modifications to database.
		 *
		 * @return int|string int for affected row count on update, string for last insert id
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function save()
		{
			if ($this->isSaved()) {
				if ($this->isModified()) {
					// update
					$t       = new OZAuthenticatorQueryReal();
					$returns = $t->safeUpdate($this->row_saved, $this->row);
				} else {
					$returns = 0;
				}
			} else {
				// add
				$columns = array_keys($this->row);
				$values  = array_values($this->row);
				$qb      = new QueryBuilder(ORM::getDatabase());
				$qb->insert()
				   ->into($this->table->getFullName(), $columns)
				   ->values($values);

				$result    = $qb->execute();
				$ai_column = $this->auto_increment_column;

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
			}

			$this->row_saved = $this->row;
			$this->is_new    = false;
			$this->saved     = true;
			$this->modified  = false;

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
		 * @return $this|\OZONE\OZ\Db\OZAuth
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
					$this->modified        = true;
					$this->saved           = false;
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
		public function __set($full_name, $value)
		{
			if (!$this->isNew()) {
				if ($this->table->hasColumn($full_name)) {
					$this->row[$full_name]       = $value;
					$this->row_saved[$full_name] = $value;
					$this->modified              = false;
				} else {
					throw new ORMException(sprintf('Could not set column "%s", not defined in table "%s".', $full_name, $this->table->getName()));
				}
			} else {
				throw new ORMException(sprintf('You should not try to manually set property for "%s".', $full_name, get_class($this)));
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