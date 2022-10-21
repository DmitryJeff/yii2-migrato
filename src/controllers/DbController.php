<?php
/**
 *
 * @author Ivo Kund <ivo@opus.ee>
 * @date 25.09.2014
 */

namespace opus\migrato\controllers;

use Symfony\Component\Yaml\Yaml;
use yii\console\Controller;
use yii\helpers\Console;

/**
 * Class DbController
 *
 * @TODO: massive code style refactor
 *
 * @author Ivo Kund <ivo@opus.ee>
 * @package console\controllers
 */
class DbController extends Controller
{
    public $defaultAction = 'run';

    /**
     * @var string Yii executable to use
     */
    public $yii = 'yii';

    /**
     * @var string PHP executable to use
     */
    public $php = 'php';

    /**
     * @var string
     */
    public $config = '@root/build/db-init.yml';

    /**
     * @param string $type init/update
     */
    public function actionRun($type = null)
    {
        $def = $this->getConfig();

        if (!isset($def['actions'][$type])) {
            $this->stdout("\nThe following commands are available:", Console::BOLD);
            foreach ($def['actions'] as $key => $action) {
                echo "\n - " . $this->ansiFormat('db ' . $key, Console::FG_YELLOW);
                echo "\n    " . $action['description'];
            }
            echo "\n\n";
        } else {
            $dsn = $this->ansiFormat(\Yii::$app->db->dsn, Console::FG_YELLOW);
            $mode = $this->ansiFormat(strtoupper($type), Console::FG_YELLOW);
            $this->stdout(sprintf("Running process in %s mode on %s\n", $mode, $dsn));
            $error = 1;
            if ($this->confirm('Continue?')) {
                $commands = $this->filterCommands($def['commands'], $type);
                $error = $this->runCommands($commands);
            }
            exit($error ? 1 : 0);
        }
    }

    /**
     * @return array
     */
    protected function getConfig()
    {
        $path = \Yii::getAlias($this->config);
        if (!is_file($path)) {
            $source = \Yii::getAlias('@vendor/opus-online/yii2-migrato/example/config-template.yml');
            $this->stdout("Created empty configuration: " . $path . "\n", Console::FG_GREEN);
            copy($source, $path);
        }
        $def = Yaml::parse(file_get_contents($path));
        return $def;
    }

    /**
     * Execute a single command
     * @param string $command
     * @return bool
     */
    protected function execute($command)
    {
        exec($command . ' 2>&1', $out, $ret);
        if ($ret) {
            echo $this->ansiFormat("Process resulted in an error:", Console::BG_RED) . "\n";
            $this->stdout(implode("\n", $out), Console::FG_RED);
        }
        return $ret === 0;
    }

    /**
     * @param array[][] $commands
     * @param string $type
     * @return array
     */
    private function filterCommands(array $commands, $type)
    {
        foreach ($commands as $component => $actions) {
            foreach ($actions as $action => $def) {
                if (isset($def['on']) && !in_array($type, $def['on'])) {
                    unset($commands[$component][$action]);
                }
            }
        }
        return array_filter($commands);
    }

    /**
     * @param array $components
     * @return bool
     */
    private function runCommands(array $components)
    {
        $error = false;
        foreach ($components as $component => $commands) {
            echo "\nApplying " . $this->ansiFormat($component, Console::FG_GREEN) . "...\n";

            foreach ($commands as $command) {
                $path = $this->resolveCommandPath($command);

                if ($error === true) {
                    $this->stdout(" - Skipping: " . $path . "\n", Console::FG_YELLOW);
                } else {
                    $this->stdout(" - " . $path . "\n", Console::FG_PURPLE);
                    if (false === $this->execute($path)) {
                        $error = true;
                    }
                }
            }
        }
        if ($error) {
            $this->stdout("\nProcess failed", Console::BG_RED);
        } else {
            $this->stdout("\nAll processes completed successfully", Console::BG_GREEN, Console::FG_BLACK);
        }
        echo "\n";

        return $error;
    }

    /**
     * @param string $command
     * @return string
     */
    private function resolveCommandPath($command)
    {
        if ($command['type'] === 'yii-exec') {
            $path = $this->getYiiExecPath($command['path']);
        } elseif ($command['type'] === 'migrate') {
            $path = $this->getYiiExecPath('migrate --interactive=0 --migrationPath=' . $command['path']);
        } else {
            $this->stdout("\nError: invalid command type: " . $command['type']);
            exit(1);
        }
        return $path;
    }

    /**
     * @param string $command
     * @return string
     */
    private function getYiiExecPath($command)
    {
        $path = sprintf('%s %s %s', $this->php, $this->yii, $command);
        return $path;
    }

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['php', 'yii', 'config']);
    }

    public function actionDropAll()
    {
        $db = \Yii::$app->db;

        $tables = $db->createCommand('SHOW TABLES')->queryColumn();

        $db->createCommand('SET foreign_key_checks=0')->execute();
        foreach ($tables as $table) {
            $db->createCommand("DROP TABLE `{$table}`")->execute();
        }
        $db->createCommand('SET foreign_key_checks=0')->execute();
    }
}
