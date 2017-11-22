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
	use OZONE\OZ\Db\OZCountriesQuery as OZCountriesQueryReal;



	/**
	 * Class OZCountry
	 *
	 * @package OZONE\OZ\Db\Base
	 */
	abstract class OZCountry extends ArrayCapable
	{
		const COL_CC2 = 'country_cc2';
		const COL_CODE = 'country_code';
		const COL_NAME = 'country_name';
		const COL_NAME_REAL = 'country_name_real';
		const COL_VALID = 'country_valid';

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
		 * OZCountry constructor.
		 *
		 * @param bool $is_new True for new entity false for entity fetched
		 *                     from the database, default is true.
		 */
		public function __construct($is_new = true)
		{
			$this->table    = ORM::getDatabase()
								 ->getTable('oz_countries');
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
		 * Getter for column `oz_countries`.`cc2`.
		 *
		 * @return string the real type is: string
		 */
		public function getCc2()
		{
		    $v = $this->_getValue(self::COL_CC2);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_countries`.`cc2`.
		 *
		 * @param string $cc2
		 *
		 * @return \OZONE\OZ\Db\OZCountry
		 */
		public function setCc2($cc2)
		{
			return $this->_setValue(self::COL_CC2, $cc2);
		}

		/**
		 * Getter for column `oz_countries`.`code`.
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
		 * Setter for column `oz_countries`.`code`.
		 *
		 * @param string $code
		 *
		 * @return \OZONE\OZ\Db\OZCountry
		 */
		public function setCode($code)
		{
			return $this->_setValue(self::COL_CODE, $code);
		}

		/**
		 * Getter for column `oz_countries`.`name`.
		 *
		 * @return string the real type is: string
		 */
		public function getName()
		{
		    $v = $this->_getValue(self::COL_NAME);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_countries`.`name`.
		 *
		 * @param string $name
		 *
		 * @return \OZONE\OZ\Db\OZCountry
		 */
		public function setName($name)
		{
			return $this->_setValue(self::COL_NAME, $name);
		}

		/**
		 * Getter for column `oz_countries`.`name_real`.
		 *
		 * @return string the real type is: string
		 */
		public function getNameReal()
		{
		    $v = $this->_getValue(self::COL_NAME_REAL);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_countries`.`name_real`.
		 *
		 * @param string $name_real
		 *
		 * @return \OZONE\OZ\Db\OZCountry
		 */
		public function setNameReal($name_real)
		{
			return $this->_setValue(self::COL_NAME_REAL, $name_real);
		}

		/**
		 * Getter for column `oz_countries`.`valid`.
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
		 * Setter for column `oz_countries`.`valid`.
		 *
		 * @param bool $valid
		 *
		 * @return \OZONE\OZ\Db\OZCountry
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
		 * @return $this|\OZONE\OZ\Db\OZCountry
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
				$t       = new OZCountriesQueryReal();
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
		 * @return $this|\OZONE\OZ\Db\OZCountry
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