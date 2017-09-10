<?php

	namespace OZONE\OZ\AppManager;

	use OZONE\OZ\Core\OZoneDb;
	use OZONE\OZ\Core\OZoneSettings;

	class Controller
	{

		private $id = null;

		public function __construct($id)
		{
			$this->id = $id;
		}

		public function exists()
		{
			return !empty($this->getInfo());
		}

		public function getId()
		{
			return $this->id;
		}

		public static function create(array $form)
		{
			$table  = $this->getTableName();
			$fields = array_keys($form);
			$values = array_values($forms);

			$sql = "INSERT INTO ? ? VALUES ?;";

			return OZoneDb::getInstance()
						  ->insert($sql, [$table, $fields, $values]);
		}

		public static function findByName($search, $from = 0, $max = 100, $cc2 = null)
		{
			$from        = is_numeric($from) ? intval($from) : 0;
			$max         = is_numeric($max) ? intval($max) : 10;
			$bind_values = ['search' => $search];
			$cc2Sql      = '';

			if (!empty($cc2)) {
				$cc2Sql             = ' AND sx_companies.company_cc2 =:cc2 ';
				$bind_values['cc2'] = $cc2;
			}

			$sql = "
			SELECT sx_companies.* 
			FROM sx_companies 
			WHERE sx_companies.company_name LIKE concat('%',:search,'%') 
				$cc2Sql 
				AND sx_companies.company_valid = 1 
			LIMIT $from,$max;";

			if (is_string($search) AND strlen($search)) {
				$req = OZoneDb::getInstance()
							  ->select($sql, $bind_values);

				$companies = OZoneDb::fetchAllWithMask($req, [
					'company_id',
					'sector_id',
					'company_name',
					'company_desc',
					'company_picid',
					'company_cc2',
					'company_time',
					'company_valid'
				], null, 'company_id');

				if (count($companies)) {
					SxTheme::addThemePropertyTo($companies, 'company');
					SxAdvertise::addAdvertisePropertyTo($companies, 'company');
				}

				return $companies;
			}

			return [];
		}

		public static function getList($from = 0, $max = 100, $valid_only = true)
		{
			$from = is_numeric($from) ? intval($from) : 0;
			$max  = is_numeric($max) ? intval($max) : 10;

			if ($valid_only) {
				$sql = "SELECT sx_companies.* FROM sx_companies WHERE sx_companies.company_valid = 1 LIMIT ?,?;";
			} else {
				$sql = "SELECT sx_companies.* FROM sx_companies WHERE 1 LIMIT ?,?;";
			}

			$req = OZoneDb::getInstance()
						  ->select($sql, [$from, $max]);

			$companies = OZoneDb::fetchAllWithMask($req, [
				'company_id',
				'sector_id',
				'company_name',
				'company_desc',
				'company_picid',
				'company_cc2',
				'company_time',
				'company_valid'
			], null, 'company_id');

			if (count($companies)) {
				SxTheme::addThemePropertyTo($companies, 'company');
				SxAdvertise::addAdvertisePropertyTo($companies, 'company');
			}

			return $companies;
		}

		public function getInfo($refresh = false)
		{
			if (empty($this->info) OR $refresh) {
				$sql = "SELECT sx_companies.* FROM sx_companies WHERE sx_companies.company_id =:companyId ;";

				$req = OZoneDb::getInstance()
							  ->select($sql, ['companyId' => $this->companyId]);

				if ($req->rowCount()) {
					$result = OZoneDb::fetchAllWithMask($req, [
						'company_id',
						'sector_id',
						'company_name',
						'company_desc',
						'company_picid',
						'company_cc2',
						'company_time',
						'company_valid'
					], null, 'company_id');

					SxTableJoinUtils::addSurveysPropertyTo($result);
					SxTheme::addThemePropertyTo($result, 'company');
					SxAdvertise::addAdvertisePropertyTo($result, 'company');

					$this->info = $result[$this->getId()];
				}
			}

			return $this->info;
		}

		public function update($fields)
		{
			$bind_values = ['picid' => $picid, 'id' => $this->getId()];

			$sql = "
				UPDATE sx_companies 
				SET sx_companies.company_picid =:picid 
				WHERE sx_companies.company_id =:id";

			return OZoneDb::getInstance()
						  ->update($sql, $bind_values);
		}
	}