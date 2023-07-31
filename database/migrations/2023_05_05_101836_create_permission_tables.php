<?php

use App\Common\CustomBlueprint;
use App\Common\CustomSchema;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

class CreatePermissionTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     * @throws Exception
     */
    public function up()
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teams = config('permission.teams');

        if (empty($tableNames)) {
            throw new Exception('Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        }
        if ($teams && empty($columnNames['team_foreign_key'] ?? null)) {
            throw new Exception('Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        }

        CustomSchema::create($tableNames['permissions'], function (CustomBlueprint $table) {
            $table->engine = 'InnoDB';
            $table->string('name');       // For MySQL 8.0 use string('name', 125);
            $table->string('guard_name')->default('web'); // For MySQL 8.0 use string('guard_name', 125);
            $table->string('description')->nullable();
            $table->audit(false);
            $table->unique(['name', 'guard_name']);
        });

        CustomSchema::create($tableNames['roles'], function (CustomBlueprint $table) use ($teams, $columnNames) {
            $table->engine = 'InnoDB';
            if ($teams || config('permission.testing')) { // permission.testing is a fix for sqlite testing
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable();
                $table->index($columnNames['team_foreign_key'], 'roles_team_foreign_key_index');
            }
            $table->string('name');       // For MySQL 8.0 use string('name', 125);
            $table->string('guard_name')->default('web'); // For MySQL 8.0 use string('guard_name', 125);
            $table->string('description')->nullable();
            $table->audit(false);
            if ($teams || config('permission.testing')) {
                $table->unique([$columnNames['team_foreign_key'], 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        CustomSchema::create($tableNames['model_has_permissions'], function (CustomBlueprint $table) use ($tableNames, $columnNames, $teams) {
            $table->unsignedInteger(PermissionRegistrar::$pivotPermission);

            $table->string('model_type');
            $table->unsignedInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign(PermissionRegistrar::$pivotPermission)
                ->references('id') // permission id
                ->on($tableNames['permissions'])
                ->onDelete('cascade');
            $table->audit(true, false);
            if ($teams) {
                $table->unsignedInteger($columnNames['team_foreign_key']);
                $table->index($columnNames['team_foreign_key'], 'model_has_permissions_team_foreign_key_index');

                $table->primary(
                    [$columnNames['team_foreign_key'], PermissionRegistrar::$pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary'
                );
            } else {
                $table->primary(
                    [PermissionRegistrar::$pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary'
                );
            }
        });

        CustomSchema::create($tableNames['model_has_roles'], function (CustomBlueprint $table) use ($tableNames, $columnNames, $teams) {
            $table->unsignedInteger(PermissionRegistrar::$pivotRole);

            $table->string('model_type');
            $table->unsignedInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign(PermissionRegistrar::$pivotRole)
                ->references('id') // role id
                ->on($tableNames['roles'])
                ->onDelete('cascade');
            $table->audit(true, false);
            if ($teams) {
                $table->unsignedInteger($columnNames['team_foreign_key']);
                $table->index($columnNames['team_foreign_key'], 'model_has_roles_team_foreign_key_index');

                $table->primary(
                    [$columnNames['team_foreign_key'], PermissionRegistrar::$pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary'
                );
            } else {
                $table->primary(
                    [PermissionRegistrar::$pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary'
                );
            }
        });

        CustomSchema::create($tableNames['role_has_permissions'], function (CustomBlueprint $table) use ($tableNames) {
            $table->unsignedInteger(PermissionRegistrar::$pivotPermission);
            $table->unsignedInteger(PermissionRegistrar::$pivotRole);

            $table->foreign(PermissionRegistrar::$pivotPermission)
                ->references('id') // permission id
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            $table->foreign(PermissionRegistrar::$pivotRole)
                ->references('id') // role id
                ->on($tableNames['roles'])
                ->onDelete('cascade');
            $table->audit(true, false);

            $table->primary([PermissionRegistrar::$pivotPermission, PermissionRegistrar::$pivotRole], 'permission_role_permission_id_role_id_primary');
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     * @throws Exception
     */
    public function down()
    {
        $tableNames = config('permission.table_names');

        if (empty($tableNames)) {
            throw new Exception('Error: config/permission.php not found and defaults could not be merged.
            Please publish the package configuration before proceeding, or drop the tables manually.');
        }

        CustomSchema::drop($tableNames['role_has_permissions']);
        CustomSchema::drop($tableNames['model_has_roles']);
        CustomSchema::drop($tableNames['model_has_permissions']);
        CustomSchema::drop($tableNames['roles']);
        CustomSchema::drop($tableNames['permissions']);
    }
}
