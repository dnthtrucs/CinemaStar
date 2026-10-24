<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class SchedulerSupervisorTest extends TestCase
{
    public function test_scheduler_restarts_after_failure_while_the_web_process_stays_alive(): void
    {
        $directory = sys_get_temp_dir().'/cinema-scheduler-'.bin2hex(random_bytes(6));
        mkdir($directory);
        file_put_contents($directory.'/running', '1');
        file_put_contents($directory.'/sleep', "#!/bin/sh\nexec /bin/sleep \"\$@\"\n");
        chmod($directory.'/sleep', 0755);
        $fakePhp = <<<'SH'
#!/bin/sh
case "$*" in
    'artisan schedule:work --no-interaction')
        echo "$PPID" >> "$SCHEDULER_TEST_DIR/pids"
        count=0
        if [ -f "$SCHEDULER_TEST_DIR/count" ]; then count=$(/bin/cat "$SCHEDULER_TEST_DIR/count"); fi
        count=$((count + 1))
        echo "$count" > "$SCHEDULER_TEST_DIR/count"
        echo "SCHEDULER_STARTED:$count"
        if [ "$count" -eq 1 ]; then exit 42; fi
        while [ -f "$SCHEDULER_TEST_DIR/running" ]; do /bin/sleep 0.05; done
        ;;
    'artisan serve '*)
        echo 'WEB_RUNNING'
        while [ -f "$SCHEDULER_TEST_DIR/running" ]; do /bin/sleep 0.05; done
        ;;
esac
exit 0
SH;
        file_put_contents($directory.'/php', $fakePhp);
        chmod($directory.'/php', 0755);
        $process = new Process(['/bin/sh', dirname(__DIR__, 2).'/deploy/start.sh'], dirname(__DIR__, 2), [
            'PATH' => $directory, 'RUN_SCHEDULER' => 'true', 'SCHEDULER_TEST_DIR' => $directory,
            'DB_DATABASE' => 'cinema_test', 'DB_URL' => '',
        ], null, 12);
        $schedulerPids = [];
        try {
            $process->start();
            $restarted = false;
            $deadline = microtime(true) + 10;
            do {
                if (str_contains($process->getOutput(), 'SCHEDULER_STARTED:2')) {
                    $restarted = true;
                    break;
                }
                usleep(50000);
            } while ($process->isRunning() && microtime(true) < $deadline);
            $this->assertTrue($restarted, $process->getOutput());
            $this->assertStringContainsString('WEB_RUNNING', $process->getOutput());
            $this->assertStringContainsString('42', $process->getOutput());
            $this->assertTrue($process->isRunning(), 'The foreground web process must remain alive.');
        } finally {
            unlink($directory.'/running');
            if (is_file($directory.'/pids')) {
                $schedulerPids = array_unique(file($directory.'/pids', FILE_IGNORE_NEW_LINES));
                foreach ($schedulerPids as $pid) {
                    if (! ctype_digit($pid) || (int) $pid <= 1) {
                        throw new \RuntimeException('Invalid scheduler fixture PID.');
                    }
                    (new Process(['/bin/sh', '-c', 'kill -TERM "$1" 2>/dev/null || true', 'scheduler-cleanup', $pid]))->run();
                }
            }
            $process->stop(1);
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
        foreach ($schedulerPids as $pid) {
            $deadline = microtime(true) + 1;
            do {
                clearstatcache(true, '/proc/'.$pid.'/stat');
                $stat = is_file('/proc/'.$pid.'/stat') ? file_get_contents('/proc/'.$pid.'/stat') : null;
                if ($stat === null || str_contains($stat, ') Z ')) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            $this->assertTrue($stat === null || str_contains($stat, ') Z '), 'Scheduler supervisor must not survive test cleanup.');
        }
    }
}
