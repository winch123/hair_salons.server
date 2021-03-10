<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\winch\YandexMapsParser;

class CallRoute extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:name';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
     * @return int
     */
    public function handle()
    {
       setExtra(2, ['bb' => 222], 'laravel_system.users');
       //query("SELECT * FROM laravel_system.users");
    }

    /*
    public function handle(YandexMapsParser $YandexMapsParser)
    {
        //$firms = json_decode(file_get_contents('/tmp/yandex_map_objs.json'));
        $firms = json_decode(file_get_contents('/tmp/a.json'));
        $YandexMapsParser->process_firms($firms);

        return 0;
    }
    */

}
