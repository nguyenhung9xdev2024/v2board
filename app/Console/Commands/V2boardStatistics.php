<?php

namespace App\Console\Commands;

use App\Models\StatServer;
use App\Models\StatUser;
use App\Models\User;
use App\Services\StatisticalService;
use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\Stat;
use App\Models\CommissionLog;
use Illuminate\Support\Facades\DB;

class V2boardStatistics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'v2board:statistics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计任务';

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
        $startAt = microtime(true);
        ini_set('memory_limit', -1);
        $this->statUser();
        $this->statServer();
        $this->stat();
        $this->info('耗时' . (microtime(true) - $startAt));
    }

    private function statServer()
    {
        $createdAt = time();
        $recordAt = strtotime('-1 day', strtotime(date('Y-m-d')));
        $statService = new StatisticalService();
        $statService->setStartAt($recordAt);
        $statService->setServerStats();
        $stats = $statService->getStatServer();
        try {
            DB::beginTransaction();
            foreach ($stats as $stat) {
                if (!StatServer::insert([
                    'server_id' => $stat['server_id'],
                    'server_type' => $stat['server_type'],
                    'u' => $stat['u'],
                    'd' => $stat['d'],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                    'record_type' => 'd',
                    'record_at' => $recordAt
                ])) {
                    throw new \Exception('stat server fail');
                }
            }
            DB::commit();
            $statService->clearStatServer();
        } catch (\Exception $e) {
            DB::rollback();
            $this->error($e->getMessage());
        }
    }

    private function statUser()
    {
        $createdAt = time();
        $recordAt = strtotime('-1 day', strtotime(date('Y-m-d')));
        $statService = new StatisticalService();
        $statService->setStartAt($recordAt);
        $statService->setUserStats();
        $stats = $statService->getStatUser();
        try {
            DB::beginTransaction();
            foreach ($stats as $stat) {
                if (!StatUser::insert([
                    'user_id' => $stat['user_id'],
                    'u' => $stat['u'],
                    'd' => $stat['d'],
                    'server_rate' => $stat['server_rate'],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                    'record_type' => 'd',
                    'record_at' => $recordAt
                ])) {
                    throw new \Exception('stat user fail');
                }
            }
            DB::commit();
            $statService->clearStatUser();
        } catch (\Exception $e) {
            DB::rollback();
            $this->error($e->getMessage());
        }
    }

    private function stat()
    {
        $endAt = strtotime(date('Y-m-d'));
        $startAt = strtotime('-1 day', $endAt);
        $statisticalService = new StatisticalService();
        $statisticalService->setStartAt($startAt);
        $statisticalService->setEndAt($endAt);
        $data = $statisticalService->generateStatData();
        $data['record_at'] = $startAt;
        $data['record_type'] = 'd';
        $statistic = Stat::where('record_at', $startAt)
            ->where('record_type', 'd')
            ->first();
        if ($statistic) {
            $statistic->update($data);
            return;
        }
        Stat::create($data);
    }
}
