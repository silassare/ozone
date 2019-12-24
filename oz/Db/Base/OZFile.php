<?php
	/**
 * Auto generated file, please don't edit.
 *
 * With: Gobl v1.0.9
 * Time: 1577196385
 */


	namespace OZONE\OZ\Db\Base;

	use Gobl\ORM\ORM;
	use Gobl\ORM\ORMEntityBase;
	use OZONE\OZ\Db\OZFilesQuery as OZFilesQueryReal;

		use OZONE\OZ\Db\OZUsersController as OZUsersControllerRealR;
	use OZONE\OZ\Db\OZFilesController as OZFilesControllerRealR;


	/**
	 * Class OZFile
	 *
	 * @package OZONE\OZ\Db\Base
	 */
	abstract class OZFile extends ORMEntityBase
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
		const COL_DATA = 'file_data';
		const COL_ADD_TIME = 'file_add_time';
		const COL_VALID = 'file_valid';

		
		/**
		 * @var \OZONE\OZ\Db\OZUser
		 */
		protected $_r_oz_file_owner;


		/**
		 * OZFile constructor.
		 *
		 * @param bool $is_new True for new entity false for entity fetched
		 *                     from the database, default is true.
		 * @param bool $strict Enable/disable strict mode
		 */
		public function __construct($is_new = true, $strict = true)
		{
			parent::__construct(ORM::getDatabase('OZONE\OZ\Db'), $is_new, $strict, OZFile::TABLE_NAME, OZFilesQueryReal::class);
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
            if (!isset($this->_r_oz_file_owner)) {
                $filters = [];
                if(!is_null($v = $this->getUserId())){
                    $filters['user_id'] = $v;
                }
                if (empty($filters)){
                    return null;
                }

                $m = new OZUsersControllerRealR();
                $this->_r_oz_file_owner = $m->getItem($filters);
            }

            return $this->_r_oz_file_owner;
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
            if(!is_null($v = $this->getId())){
                $filters['file_clone'] = $v;
            }
            if (empty($filters)){
                return [];
            }

            $ctrl = new OZFilesControllerRealR();

            return $ctrl->getAllItems($filters, $max, $offset, $order_by, $total);
        }

		
		/**
		 * Getter for column `oz_files`.`id`.
		 *
		 * @return string the real type is: bigint
		 */
		public function getId()
		{
			$column = self::COL_ID;
		    $v = $this->$column;

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
		 * @return static
		 */
		public function setId($id)
		{
			$column = self::COL_ID;
			$this->$column = $id;

			return $this;
		}

		/**
		 * Getter for column `oz_files`.`user_id`.
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
		 * Setter for column `oz_files`.`user_id`.
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
		 * Getter for column `oz_files`.`key`.
		 *
		 * @return string the real type is: string
		 */
		public function getKey()
		{
			$column = self::COL_KEY;
		    $v = $this->$column;

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
		 * @return static
		 */
		public function setKey($key)
		{
			$column = self::COL_KEY;
			$this->$column = $key;

			return $this;
		}

		/**
		 * Getter for column `oz_files`.`clone`.
		 *
		 * @return string the real type is: bigint
		 */
		public function getClone()
		{
			$column = self::COL_CLONE;
		    $v = $this->$column;

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
		 * @return static
		 */
		public function setClone($clone)
		{
			$column = self::COL_CLONE;
			$this->$column = $clone;

			return $this;
		}

		/**
		 * Getter for column `oz_files`.`origin`.
		 *
		 * @return string the real type is: bigint
		 */
		public function getOrigin()
		{
			$column = self::COL_ORIGIN;
		    $v = $this->$column;

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
		 * @return static
		 */
		public function setOrigin($origin)
		{
			$column = self::COL_ORIGIN;
			$this->$column = $origin;

			return $this;
		}

		/**
		 * Getter for column `oz_files`.`size`.
		 *
		 * @return string the real type is: bigint
		 */
		public function getSize()
		{
			$column = self::COL_SIZE;
		    $v = $this->$column;

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
		 * @return static
		 */
		public function setSize($size)
		{
			$column = self::COL_SIZE;
			$this->$column = $size;

			return $this;
		}

		/**
		 * Getter for column `oz_files`.`type`.
		 *
		 * @return string the real type is: string
		 */
		public function getType()
		{
			$column = self::COL_TYPE;
		    $v = $this->$column;

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
		 * @return static
		 */
		public function setType($type)
		{
			$column = self::COL_TYPE;
			$this->$column = $type;

			return $this;
		}

		/**
		 * Getter for column `oz_files`.`name`.
		 *
		 * @return string the real type is: string
		 */
		public function getName()
		{
			$column = self::COL_NAME;
		    $v = $this->$column;

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
		 * @return static
		 */
		public function setName($name)
		{
			$column = self::COL_NAME;
			$this->$column = $name;

			return $this;
		}

		/**
		 * Getter for column `oz_files`.`label`.
		 *
		 * @return string the real type is: string
		 */
		public function getLabel()
		{
			$column = self::COL_LABEL;
		    $v = $this->$column;

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
		 * @return static
		 */
		public function setLabel($label)
		{
			$column = self::COL_LABEL;
			$this->$column = $label;

			return $this;
		}

		/**
		 * Getter for column `oz_files`.`path`.
		 *
		 * @return string the real type is: string
		 */
		public function getPath()
		{
			$column = self::COL_PATH;
		    $v = $this->$column;

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
		 * @return static
		 */
		public function setPath($path)
		{
			$column = self::COL_PATH;
			$this->$column = $path;

			return $this;
		}

		/**
		 * Getter for column `oz_files`.`thumb`.
		 *
		 * @return string the real type is: string
		 */
		public function getThumb()
		{
			$column = self::COL_THUMB;
		    $v = $this->$column;

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
		 * @return static
		 */
		public function setThumb($thumb)
		{
			$column = self::COL_THUMB;
			$this->$column = $thumb;

			return $this;
		}

		/**
		 * Getter for column `oz_files`.`data`.
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
		 * Setter for column `oz_files`.`data`.
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
		 * Getter for column `oz_files`.`add_time`.
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
		 * Setter for column `oz_files`.`add_time`.
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
		 * Getter for column `oz_files`.`valid`.
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
		 * Setter for column `oz_files`.`valid`.
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