<?php

class MigrateRelationsTask extends BuildTask {
	protected $title = 'Migrate Relations';
	protected $description = 'Migrates specific relations defined in yml';
	protected $enabled = true;

	public function run($request) {
		echo $this->performMigration();
	}

	/**
	 * Queries the config for Migrate definitions, and runs migrations
	 *
	 * Supported types:
	 * * remove: removes empty obsolete tables
	 * * has_one: moves a has_one relationship from one dataobject to another, with all it's data. Renames old relation in DB to
	 * *          to obsolete_[fieldname]
	 * * many_many: removes the autogenerated table (if empty), renames the existing table for the new relation owner,
	 * *			renames the existing relation in the many_many table to match the new relation
	 *
	 * @return HTML | result of migrations
	 */
	public function performMigration() {

		$remove = Config::inst()->get('Migrate', 'remove_table');
		$db_field = Config::inst()->get('Migrate', 'db_field');
		$has_one = Config::inst()->get('Migrate', 'has_one');
		$many_many = Config::inst()->get('Migrate', 'many_many');

		$result = '';

		if($remove) {
			$result .= '<h2>Removing obsolete tables</h2>';
			foreach ($remove as $field) {
				$result .= $this->dropUnwantedTable($field['table_name']); // old table pre migration
			}
		}

		if($db_field) {
			$result .= '<h2>Moving db_fields</h2>';
			foreach ($db_field as $field) {
				$result .= $this->migrateSimple($field['owner_current'], $field['owner_new'], $field['field_name_current'], $field['field_name_new'], $field['field_type']);
			}
		}

		if($has_one) {
			$result .= '<h2>Moving has_one relations</h2>';
			foreach ($has_one as $field) {
				$result .= $this->migrateSimple($field['owner_current'], $field['owner_new'], $field['field_name_current'], $field['field_name_new']);
			}
		}

		if($many_many) {
			$result .= '<h2>Migrating many_many relations</h2>';
				foreach ($many_many as $field) {
				$result .= $this->migrateManyMany($field['owner_current'], $field['owner_new'], $field['field_name']);
			}
		}

		$result .= "<h2 style='font-size: 60px;'>Finished!  &#x1f37b;</h2>";

		return $result;
	}

	/**
	 * Migrates a db or has_one field, and all its data, from one table to another
	 * Renames old field in DB to obsolete_[$fieldNameCurrent] to avoid name conflicts
	 *
	 * @param String | $tableCurrent - the db table where we are moving a field from
	 * @param String | $tableNew - the db table where we are moving a field to
	 * @param String | $fieldNameCurrent - The current field name
	 * @param String | $fieldNameNew - The new field name (this may be the same as $fieldNameCurrent)
	 * @return HTML | result of migrations
	 */
	public function migrateSimple($tableCurrent, $tableNew, $fieldNameCurrent, $fieldNameNew, $type="INT") {

		try {
			$sqlResults = DB::query('SELECT 1 FROM "'. $tableNew . '" LIMIT 1');

		} catch(Exception $e) {
			$result = "<p>$tableNew does not exist yet</p>";
			return $result;
		}

		try {
			$result = '';
			$count = 0;
			$sqlResults = DB::query('SELECT "'.$fieldNameCurrent.'", "ID" FROM "'. $tableCurrent .'"');

			foreach($sqlResults as $sqlResult) {
				DB::query('UPDATE "'. $tableNew .'" SET "'.$fieldNameNew.'" = \''. $sqlResult[$fieldNameCurrent] .'\' WHERE ID = '. $sqlResult['ID']);
				$count++;
			}

			DB::query('ALTER TABLE "' . $tableCurrent . '" CHANGE COLUMN "' . $fieldNameCurrent . '" "_obsolete_' . $fieldNameCurrent . '" '. $type);

			$result .= "<p style='color: green'>Migration successful. $count relations were migrated to $tableNew. <br /> $fieldNameCurrent renamed to _obsolete_$fieldNameCurrent in $tableCurrent</p>";

		} catch(Exception $e) {
			$result = "<p>Unable to migrate $fieldNameCurrent from $tableCurrent to $tableNew. Does it already exist?</p>";
			$result .= "<ul><li>Errored with message: <span style='color: gray'>";
			$result .= $e->getMessage();
			$result .= '</span></li></ul>';
		}

		return $result;
	}

	/**
	 * Initiates the migration of a many_many relation
	 *
	 * @param String | $ownerCurrent - where we are moving the relation from
	 * @param String | $ownerNew - where we are moving the relation to
	 * @param String | $fieldName - what the relationship is with (e.g $Owner->[RelationName()])
	 * @return HTML | result of migrations
	 */
	public function migrateManyMany($ownerCurrent, $ownerNew, $fieldName) {
		$tableCurrent = $ownerCurrent . '_' . $fieldName;
		$tableNew = $ownerNew . '_' . $fieldName;

		$result = '<p>Migrating many many: <b>' . $ownerCurrent . '_' . $fieldName . '</b> to <b>'. $ownerNew .'_' . $fieldName . '</b></p>';
		$result .= '<ol>';
		$result .= '<li>Deleting auto-generated tables: ';
		$result .= $this->dropUnwantedTable($tableNew) . '</li>';

		$result .= '<li>Renaming old tables: ';
		$result .= $this->renameTable($tableCurrent, $tableNew) . '</li>';

		$result .= '<li>Renaming old relations: ';
		$result .= $this->renameField($tableNew, $ownerCurrent . 'ID', $ownerNew .'ID') . '</li>';
		$result .= '</ol>';

		return $result;
	}

	/**
	 * Drops unwanted, and empty, tables from the db
	 *
	 * @param String | $tableName - table to drop
	 * @return HTML | result of attempt to drop the table
	 */
	public function dropUnwantedTable($tableName) {
		$result = '';

		try {
			$oldtable = DB::query('SELECT * FROM "'. $tableName .'"');

			if($oldtable->numRecords() < 1) {
				DB::query('DROP TABLE IF EXISTS "'. $tableName .'"');
				$result .= "<em style='color: green'>$tableName Table deleted</em>";
			} else {
				$result .= "<em style='color: red'>$tableName Table has data - not safe to delete</em>";
			}

		} catch(Exception $e) {
			$result .= "<em>$tableName Table already deleted</em>";
		}

		return $result;
	}

	/**
	 * Renames a table. Used by the many_many migration
	 *
	 * @param String | $tableNameCurrent - table to rename
	 * @param String | $tableNameNew - new name of table
	 * @return HTML | result of attempt to drop the table
	 */
	public function renameTable($tableNameCurrent, $tableNameNew) {
		$result = '';

		try {
			$oldtable = DB::query('SELECT * FROM "'. $tableNameCurrent .'"');

			DB::query('RENAME TABLE "'. $tableNameCurrent .'" TO "'. $tableNameNew .'"');
			$result .= "<em style='color: green'>$tableNameCurrent Table renamed to $tableNameNew</em>";

		} catch(Exception $e) {
			$result .= "<em>$tableNameCurrent Table doesn't exist. It might have been renamed already?</em>";
		}

		return $result;
	}

	/**
	 * Renames a field. Used by the many_many migration
	 *
	 * @param String | $tableName - table field exists in
	 * @param String | $fieldCurrent - existing field name
	 * @param String | $fieldNew - what we want to rename our field to
	 * @return HTML | result of attempt to drop the table
	 */
	public function renameField($tableName, $fieldCurrent, $fieldNew) {
		try {
			$result = '';
			$sqlResults = DB::query('SELECT "'. $fieldCurrent .'" FROM "'. $tableName .'"');

			DB::query('ALTER TABLE "'. $tableName .'"
				CHANGE COLUMN "'. $fieldCurrent .'" "'. $fieldNew .'" INT');

			$result .= "<em style='color: green'>$fieldCurrent is now $fieldNew</em>";

		} catch(Exception $e) {
			$result = "<em>Failed. Field may not exist. Has this migration already been run?</em>";
			$result .= "<ul><li>Errored with message: <span style='color: gray'>";
			$result .= $e->getMessage();
			$result .= '</span></li></ul>';
		}

		return $result;
	}
}
