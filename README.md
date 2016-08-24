# silverstripe-migraterelations
Migrates specific silverstripe relations defined in yml
 
## How to use
1 - Update your classes to use the new relations.  
2 - Define the fields you want to migrate in a yml file. eg: 
```
---
Name: migration
After:
  - 'framework/*'
  - 'cms/*'
---
Migrate:
 remove_table:
   0:
     table_name: AnEmptyObsoleteTable
 db_field:
   0:
     owner_current: 'CurrentOwnerClassName'
     owner_new: 'NewOwnerClassName'
     field_name_current: CurrentDBFieldName
     field_name_new: NewDBFieldName
     field_type: VARCHAR(255)
 has_one:
   0:
     owner_current: 'CurrentHasOneClassName'
     owner_new: 'NewHasOneClassName'
     field_name_current: CurrentDBFieldName
     field_name_new: NewDBFieldName
   1:
     owner_current: 'OtherCurrentHasOneClassName'
     owner_new: 'OtherNewHasOneClassName'
     field_name_current: OtherCurrentDBFieldName
     field_name_new: OtherNewDBFieldName
 many_many:
   0:
     owner_current: 'CurrentManyManyOwnerClassName'
     owner_new: 'NewManyManyOwnerClassName'
     field_name: 'RelationName'
```


3 - Run dev/tasks/MigrateRelationsTask


## TODO
* Make this task extend MigrationTask and provide rollback options
* Tests
