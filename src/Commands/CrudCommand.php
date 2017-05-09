<?php

namespace Agustind\Crud\Commands;

use Illuminate\Console\Command;

class CrudCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crud:generate {table?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates CRUD for a given database table';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        // ------ get table from input
        $table = $this->argument('table');
        if(empty($table))
            $table = $this->ask('Which database table would you like to use?');
        // ------------------------------


        $plural = $table;
        $singular = str_singular($table);
        $model = studly_case($singular);


        // if model doesnt exist, create model
        if(!file_exists('app/' . $model . '.php')){
            $model_content = '<?php
                namespace App;
                use Illuminate\Database\Eloquent\Model;
                class ' . $model . ' extends Model
                {
                    //
                }
            ';
            \File::put('app/' . $model . '.php', $model_content);
            $this->info(' Model ' . $model . ' created');
        }

        $controller_name = studly_case($table) . 'Controller';
        $controller_filename = 'app/Http/Controllers/' . $controller_name . '.php';

        if(file_exists($controller_filename)){
            $this->error($controller_name . " already exists \n");
            die();
        }

        $views_directory = 'resources/views/' . $plural;

        if(file_exists($views_directory)){
            $this->error($views_directory . " already exists \n");
            die();
        }

        $columns = \DB::select( \DB::raw('SHOW COLUMNS FROM `' . $table . '`'));

        // generate the controller
        $field_assignments = '';
        foreach($columns as $column){
            $field = $column->Field;
            $type = $column->Type;
            if($field != 'id' && $field != 'created_at' && $field != 'updated_at'){
                $field_assignments .= "\$" . str_singular($table) . "->" . $field . " = request('" . $field . "');
                    ";    
            }
        }

        $controller_content = '<?php

            namespace App\Http\Controllers;

            use Illuminate\Http\Request;
            use App\\' . $model . ';

            class ' . $controller_name . ' extends Controller
            {

                // listing
                public function index(){
                    $' . $plural . ' = ' . $model . '::all();
                    return view("' . $plural . '/index", compact("' . $plural . '"));
                }

                // show
                public function show(' . $model . ' $' . $singular . '){
                    return view("' . $plural . '/show", compact("user"));
                }

                // create
                public function create(){
                    return view("' . $plural . '/create");
                }

                // store
                public function store(){
                    $' . $singular . ' = new ' . $model . ';
                    ' . $field_assignments . '
                    $' . $singular . '->save();
                    return redirect("/' . $plural . '");
                }

                // edit
                public function edit(' . $model . ' $' . $singular . '){
                    return view("' . $plural . '/edit", compact(\'' . $singular . '\'));
                }

                // update
                public function update(' . $model . ' $' . $singular . '){
                    ' . $field_assignments . '
                    $' . $singular . '->save();
                    return redirect("/' . $plural . '");
                }

                // delete
                public function delete(' . $model . ' $' . $singular . '){
                    $' . $singular . '->delete();
                    return redirect("/' . $plural . '");
                }

            }
        ';
        
        // save controller file
        \File::put($controller_filename, $controller_content);
        $this->info($controller_name . ' created');
        
        
        // generate the views
        $layout_open = '';
        $layout_close = '';

        if(file_exists('resources/views/layouts/app.blade.php')){
            $layout_open = '@extends(\'layouts.app\')
        @section(\'content\')';
            $layout_close = '@endsection';
        }

        // index view

        $view_index_headers = '<tr>';
        foreach($columns as $column){
            $field = $column->Field;
            $view_index_headers .= '
                <th>' . ucfirst($field) . '</th>
            ';
        }
        $view_index_headers .= '<th></th></tr>';


        $view_index_columns = '';
        foreach($columns as $column){
            $field = $column->Field;
            $view_index_columns .= '
                <td>{{ $' . $singular . '->' . $field . ' }}</td>
            ';
        }
        

        $index_view = $layout_open . '
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <div class="panel panel-default">
                            <div class="panel-body">
                                <h2>' . ucfirst($plural) . '</h2>
                                <a href="/' . $plural . '/create" class="btn btn-primary">Create new ' . $singular . '</a><br><br>
                                @if(count($' . $plural . ') > 0)
                                    <table class="table">
                                        ' . $view_index_headers . '
                                        @foreach($' . $plural . ' as $' . $singular . ')
                                            <tr>
                                                ' . $view_index_columns . '
                                                <td><a href="/' . $plural . '/{{ $' . $singular . '->id }}/edit">edit</a> | <a href="javascript:if(confirm(\'Are you sure you want to delete this ' . $singular . '\')){ document.location=\'/' . $plural . '/{{ $' . $singular . '->id }}/delete\' }">delete</a></td>
                                            </tr>
                                        @endforeach
                                    </table>
                                @else
                                    There are no ' . $plural . ' yet
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        ' . $layout_close;

        // create views dir
        mkdir($views_directory);

        \File::put($views_directory . '/index.blade.php', $index_view);
        $this->info($views_directory . '/index.blade.php' . ' created');
        

        // create the 'create' view

        $create_form_fields = '';
        foreach($columns as $column){
            $field = $column->Field;
            $type = $column->Type;
            
            if(strstr($type, 'varchar')){
                $create_form_fields .= '<div class="form-group">
                    <label for="name" class="control-label">' . studly_case($field) . '</label>
                    <input type="text" name="' . $field . '" class="form-control"></div>
                ';
            }

            if(strstr($type, 'text')){
                $create_form_fields .= '<div class="form-group">
                    <label for="name" class="control-label">' . studly_case($field) . '</label>
                    <textarea name="' . $field . '" class="form-control"></textarea></div>
                ';
            }
        } 

        $create_view = $layout_open . '
                <div class="container">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="panel panel-default">
                                <div class="panel-body">
                                    <h2>Create ' . $singular . '</h2>
                                    <form action="/' . $plural . '" method="POST">
                                        {{ csrf_field() }}
                                        ' . $create_form_fields . '
                                        <div class="form-group">
                                            <a href="/' . $plural . '" class="btn btn-default">cancel</a>
                                            <input type="submit" value="Create new ' . $singular . '" class="btn btn-primary">
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        ' . $layout_close;

        \File::put($views_directory . '/create.blade.php', $create_view);
        $this->info($views_directory . '/create.blade.php' . ' created');


        // create the 'edit' view

        $edit_form_fields = '';
        foreach($columns as $column){
            $field = $column->Field;
            $type = $column->Type;
            
            if(strstr($type, 'varchar')){
                $edit_form_fields .= '<div class="form-group">
                    <label for="name" class="control-label">' . studly_case($field) . '</label>
                    <input type="text" name="' . $field . '" class="form-control" value="{{ $' . $singular . '->' . $field . ' }}"></div>
                ';
            }

            if(strstr($type, 'text')){
                $edit_form_fields .= '<div class="form-group">
                    <label for="name" class="control-label">' . studly_case($field) . '</label>
                    <textarea name="' . $field . '" class="form-control">{{ $' . $singular . '->' . $field . ' }}</textarea></div>
                ';
            }
        } 

        $edit_view = $layout_open . '
            
                <div class="container">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="panel panel-default">
                                <div class="panel-body">
                                    <h2>Edit ' . $singular . '</h2>
                                    <form action="/' . $plural . '/{{ $' . $singular . '->id }}" method="POST">
                                        {{ csrf_field() }}
                                        ' . $edit_form_fields . '
                                        <div class="form-group">
                                            <a href="/' . $plural . '" class="btn btn-default">cancel</a>
                                            <input type="submit" value="Save ' . $singular . '" class="btn btn-primary">
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        ' . $layout_close;

        \File::put($views_directory . '/edit.blade.php', $edit_view);
        $this->info($views_directory . '/edit.blade.php' . ' created');

        // write to routes file
        $routes_content = '

            Route::get(\'/' . $plural . '\', \'' . $controller_name . '@index\');
            Route::get(\'/' . $plural . '/create\', \'' . $controller_name . '@create\');
            Route::post(\'/' . $plural . '\', \'' . $controller_name . '@store\');
            Route::get(\'/' . $plural . '/{' . $singular . '}/edit\', \'' . $controller_name . '@edit\');
            Route::post(\'/' . $plural . '/{' . $singular . '}\', \'' . $controller_name . '@update\');
            Route::get(\'/' . $plural . '/{' . $singular . '}/delete\', \'' . $controller_name . '@delete\');
    
        ';
        \File::append('routes/web.php', $routes_content);


    }
}
