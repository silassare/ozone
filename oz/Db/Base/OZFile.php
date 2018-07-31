<?php
	/**
 * Auto generated file, please don't edit.
 *
 * With: Gobl v1.0.0
 * Time: 1533007932
 */

	namespace OZONE\OZ\Db\Base;

	use Gobl\DBAL\QueryBuilder;
	use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
	use Gobl\ORM\ArrayCapable;
	use Gobl\ORM\Exceptions\ORMException;
	use Gobl\ORM\ORM;
	use OZONE\OZ\Db\OZFilesQuery as OZFilesQueryReal;

		use OZONE\OZ\Db\OZUsersController as OZUsersControllerRealR;
	use OZONE\OZ\Db\OZFilesController as OZFilesControllerRealR;


	/**
	 * Class OZFile
	 *
	 * @package OZONE\OZ\Db\Base
	 */
	abstract class OZFile extends ArrayCapable
	{
		const TABLE_NAME = 'oz_files';

				const COL_ID = 'file_id';
		const COL_USER_ID = 'file_user_id';
		const COL_KEY = 'file_key';
		const COL_CLONE = 'file_clone';
		const COL_ORIGIN = 'file_origin';
		const COL_SIZE = 'file_size';
		const COL_TYPE = 'file_type';
		const COL_NAME = 'file_name';
		const COL_LABEL = 'file_label';
		const COL_PATH = 'file_path';
		const COL_THUMB = 'file_thumb';
		const COL_UPLOAD_TIME = 'file_upload_time';

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
		 * To enable/disable strict mode.
		 *
		 * @var bool
		 */
		protected $strict = true;

		/**
		 * Private columns
		 *
		 * @var array
		 */
		protected static $private_columns = [
			
		];

		
		/**
		 * @var \OZONE\OZ\Db\OZUser
		 */
		protected $r_oz_file_owner;


		/**
		 * OZFile constructor.
		 *
		 * @param bool $is_new True for new entity false for entity fetched
		 *                     from the database, default is true.
		 * @param bool $strict
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function __construct($is_new = true, $strict = true)
		{
			$this->table    = ORM::getDatabase()
								 ->getTable(OZFile::TABLE_NAME);
			$columns        = $this->table->getColumns();
			$this->is_new   = (bool)$is_new;
			$this->is_saved = !$this->is_new;
			$this->strict   = (bool)$strict;

			// we initialise row with default value
			foreach ($columns as $column) {
				$full_name             = $column->getFullName();
				$type                  = $column->getTypeObject();
				$this->row[$full_name] = $type->getDefault();

				// the auto_increment column
				if ($type->isAutoIncremented()) {
					$this->auto_increment_column = $full_name;
				}
			}
		}
		
        /**
         * ManyToOne relation between `oz_files` and `oz_users`.
         *
         * @return null|\OZONE\OZ\Db\OZUser
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
         */
        public function getOZFileOwner()
        {
            if (!isset($this->r_oz_file_owner)) {

                $filters['user_id'] = $this->getUserId();

                $m = new OZUsersControllerRealR(true);
                $this->r_oz_file_owner = $m->getItem($filters);
            }

            return $this->r_oz_file_owner;
        }

        /**
         * OneToMany relation between `oz_files` and `oz_files`.
         *
         * @param array    $filters  the row filters
         * @param int|null $max      maximum row to retrieve
         * @param int      $offset   first row offset
         * @param array    $order_by order by rules
         * @param int|bool $total    total rows without limit
         *
         * @return \OZONE\OZ\Db\OZFile[]
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
         */
        function getOZFileClones($filters = [], $max = null, $offset = 0, $order_by = [], &$total = false)
        {

            $filters['file_clone'] = $this->getId();

            $ctrl = new OZFilesControllerRealR(true);

            return $ctrl->getAllItems($filters, $max, $offset, $order_by, $total);
        }

		
		/**
		 * Getter for column `oz_files`.`id`.
		 *
		 * @return string the real type is: bigint
		 */
		public function getId()
		{
		    $v = $this->_getValue(self::COL_ID);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_files`.`id`.
		 *
		 * @param string $id
		 *
		 * @return \OZONE\OZ\Db\OZFile
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 */
		public function setId($id)
		{
			return $this->_setValue(self::COL_ID, $id);
		}

		/**
		 * Getter for column `oz_files`.`user_id`.
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
		 * Setter for column `oz_files`.`user_id`.
		 *
		 * @param string $user_id
		 *
		 * @return \OZONE\OZ\Db\OZFile
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 */
		public function setUserId($user_id)
		{
			return $this->_setValue(self::COL_USER_ID, $user_id);
		}

		/**
		 * Getter for column `oz_files`.`key`.
		 *
		 * @return string the real type is: string
		 */
		public function getKey()
		{
		    $v = $this->_getValue(self::COL_KEY);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_files`.`key`.
		 *
		 * @param string $key
		 *
		 * @return \OZONE\OZ\Db\OZFile
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 */
		public function setKey($key)
		{
			return $this->_setValue(self::COL_KEY, $key);
		}

		/**
		 * Getter for column `oz_files`.`clone`.
		 *
		 * @return string the real type is: bigint
		 */
		public function getClone()
		{
		    $v = $this->_getValue(self::COL_CLONE);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_files`.`clone`.
		 *
		 * @param string $clone
		 *
		 * @return \OZONE\OZ\Db\OZFile
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 */
		public function setClone($clone)
		{
			return $this->_setValue(self::COL_CLONE, $clone);
		}

		/**
		 * Getter for column `oz_files`.`origin`.
		 *
		 * @return string the real type is: bigint
		 */
		public function getOrigin()
		{
		    $v = $this->_getValue(self::COL_ORIGIN);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_files`.`origin`.
		 *
		 * @param string $origin
		 *
		 * @return \OZONE\OZ\Db\OZFile
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 */
		public function setOrigin($origin)
		{
			return $this->_setValue(self::COL_ORIGIN, $origin);
		}

		/**
		 * Getter for column `oz_files`.`size`.
		 *
		 * @return string the real type is: bigint
		 */
		public function getSize()
		{
		    $v = $this->_getValue(self::COL_SIZE);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_files`.`size`.
		 *
		 * @param string $size
		 *
		 * @return \OZONE\OZ\Db\OZFile
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 */
		public function setSize($size)
		{
			return $this->_setValue(self::COL_SIZE, $size);
		}

		/**
		 * Getter for column `oz_files`.`type`.
		 *
		 * @return string the real type is: string
		 */
		public function getType()
		{
		    $v = $this->_getValue(self::COL_TYPE);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_files`.`type`.
		 *
		 * @param string $type
		 *
		 * @return \OZONE\OZ\Db\OZFile
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 */
		public function setType($type)
		{
			return $this->_setValue(self::COL_TYPE, $type);
		}

		/**
		 * Getter for column `oz_files`.`name`.
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
		 * Setter for column `oz_files`.`name`.
		 *
		 * @param string $name
		 *
		 * @return \OZONE\OZ\Db\OZFile
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 */
		public function setName($name)
		{
			return $this->_setValue(self::COL_NAME, $name);
		}

		/**
		 * Getter for column `oz_files`.`label`.
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
		 * Setter for column `oz_files`.`label`.
		 *
		 * @param string $label
		 *
		 * @return \OZONE\OZ\Db\OZFile
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 */
		public function setLabel($label)
		{
			return $this->_setValue(self::COL_LABEL, $label);
		}

		/**
		 * Getter for column `oz_files`.`path`.
		 *
		 * @return string the real type is: string
		 */
		public function getPath()
		{
		    $v = $this->_getValue(self::COL_PATH);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_files`.`path`.
		 *
		 * @param string $path
		 *
		 * @return \OZONE\OZ\Db\OZFile
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 */
		public function setPath($path)
		{
			return $this->_setValue(self::COL_PATH, $path);
		}

		/**
		 * Getter for column `oz_files`.`thumb`.
		 *
		 * @return string the real type is: string
		 */
		public function getThumb()
		{
		    $v = $this->_getValue(self::COL_THUMB);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_files`.`thumb`.
		 *
		 * @param string $thumb
		 *
		 * @return \OZONE\OZ\Db\OZFile
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 */
		public function setThumb($thumb)
		{
			return $this->_setValue(self::COL_THUMB, $thumb);
		}

		/**
		 * Getter for column `oz_files`.`upload_time`.
		 *
		 * @return string the real type is: bigint
		 */
		public function getUploadTime()
		{
		    $v = $this->_getValue(self::COL_UPLOAD_TIME);

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_files`.`upload_time`.
		 *
		 * @param string $upload_time
		 *
		 * @return \OZONE\OZ\Db\OZFile
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 */
		public function setUploadTime($upload_time)
		{
			return $this->_setValue(self::COL_UPLOAD_TIME, $upload_time);
		}

		/**
		 * Hydrate this entity with values from an array.
		 *
		 * @param array $row map column name to column value
		 *
		 * @return $this|\OZONE\OZ\Db\OZFile
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
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
		 * @throws \Gobl\DBAL\Exceptions\DBALException
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
				$t       = new OZFilesQueryReal();
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
		 * @return $this|\OZONE\OZ\Db\OZFile
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 */
		protected function _setValue($name, $value)
		{
			if ($this->table->hasColumn($name)) {
				$column = $this->table->getColumn($name);
				$type   = $column->getTypeObject();

				try {
					$value = $type->validate($value, $column->getName(), $this->table->getName());
				} catch (TypesInvalidValueException $e) {
					$debug = [
						"field"      => $column->getFullName(),
						"table_name" => $this->table->getName(),
						"options"    => $type->getCleanOptions()
					];

					$e->setDebugData($debug);

					throw $e;
				}

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
			} elseif ($this->strict) {
				throw new ORMException(sprintf('Could not set column "%s", not defined in table "%s".', $full_name, $this->table->getName()));
			}
		}

		/**
		 * {@inheritdoc}
		 */
		public function asArray($hide_private_column = true)
		{
			$row = $this->row;

			if ($hide_private_column) {
				foreach (self::$private_columns as $key => $value) {
					unset($row[$key]);
				}
			}

			return $row;
		}
	}