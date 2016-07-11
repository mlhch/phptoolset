<?php

if (!function_exists('pre')):
function pre($var) {
    $args = func_get_args();
    $exit = 1 === $args[count($args) - 1];
    echo "<pre>------\n";
    foreach ($args as $i => $arg) {
        if (is_string($arg)) {
            echo "#$i: string(" . strlen($arg) . "), $arg\n";
        } elseif (is_bool($arg)) {
            echo "#$i: " . gettype($arg) . ", " . ($arg ? 'true' : 'false') . "\n";
        } elseif (is_int($arg) || is_float($arg)) {
            echo "#$i: " . gettype($arg) . ", $arg\n";
        } elseif (is_null($arg)) {
            echo "#$i: null\n";
        } elseif (is_object($arg)) {
            echo "#$i: &lt;" . get_class($arg) . "&gt;\n";
        } elseif (is_array($arg)) {
            echo "#$i: array(" . count($arg) . ")\n";
        } else {
            echo "#$i: " . gettype($arg) . ", $arg\n";
        }
    }
    echo ">>>>>>\n";
    if (is_object($args[0])) {
        if (isset($args[1]) && is_string($args[1])) {
            Reflection::export(new ReflectionMethod($args[0], $args[1]));
        } else {
            u_print_r($var);
        }
    } elseif (is_string($var) && class_exists($var)) {
        if (is_string($args[1])) {
            Reflection::export(new ReflectionMethod($args[0], $args[1]));
        } else {
            var_dump(Reflection::export(new ReflectionClass($var)));
        }
    } elseif (is_string($var) && function_exists($var)) {
        Reflection::export(new ReflectionFunction($var));
    } elseif (is_null($var)) {
        echo "null";
    } else {
        print_r($var);
    }
    echo "<br />======";
    if ($exit) {
        $rows = debug_backtrace();
        echo "\n";
        foreach ($rows as $row) {
            $class  = empty($row['class']) ? '' : $row['class'];
            $type   = empty($row['type']) ? '' : $row['type'];
            $file   = empty($row['file']) ? '' : $row['file'];
            $file   = str_replace('vendor/laravel/framework/', '', $file);
            $file   = substr(str_replace('/var/www/html/', '', $file), -65);
            $line   = empty($row['line']) ? '' : $row['line'];
            echo sprintf("%s@%s\t\t%s -> %s\n", $file, $line, $class, $row['function']);
        }
        echo '</pre>';
        exit;
    }
    echo '<' . '/pre>';
}

function u_print_r($subject, $ignore = array(), $depth = 1, $refChain = array()) {
    if ($depth > 2) {
        if (is_object($subject)) {
            echo '&lt;' . get_class($subject) . '&gt;';
        } elseif (is_array($subject)) {
            echo "Array(" . count($subject) . ")";
        } else {
            echo gettype($subject) . "($subject)";
        }
        return;
    }
    if (is_object($subject)) {
        foreach ($refChain as $refVal)
            if ($refVal === $subject) {
                echo "*RECURSION*\n";
                return;
            }
        array_push($refChain, $subject);
        echo get_class($subject) . " Object ( \n";
        $subject = (array) $subject;
        foreach ($subject as $key => $val)
            if (is_array($ignore) && !in_array($key, $ignore, 1)) {
                // $key 是 "\0*\0 user" 这样的格式，* 表示 protected，无表示 private
                // \0 在字符串连接时不被显示
                $parts  = explode("\0", $key);
                if (isset($parts[1])) {
                    $key    = "$parts[1] $parts[2]";
                }
                echo str_repeat(" ", $depth * 4) . '[' . $key . '] => ';
                u_print_r($val, $ignore, $depth + 1, $refChain);
                echo "\n";
            }
        echo str_repeat(" ", ($depth - 1) * 4) . ")\n";
        array_pop($refChain);
    } elseif (is_array($subject)) {
        echo "Array (\n";
        foreach ($subject as $key => $val)
            if (is_array($ignore) && !in_array($key, $ignore, 1)) {
                echo str_repeat(" ", $depth * 4) . '[' . $key . '] => ';
                u_print_r($val, $ignore, $depth + 1, $refChain);
                echo "\n";
            }
        echo str_repeat(" ", ($depth - 1) * 4) . ")\n";
    } else
        echo $subject . "\n";
}
endif;


function time_elapsed($msg, $output = false) {
    static $orig = null;
    static $maps = array();
    static $rows = array();
    static $rank = array();
    static $level = 0;
    static $levels = array();
    static $isleaf = array();
    $now = microtime(true);
    if ($orig == null) $orig = $now;
    $now -= $orig;

    $levels[$level] = $now;
    $isleaf[$level] = true;

    ## 步入时
    if (empty($maps[$msg])) {
        ##
        $maps[$msg] = [$now, $now - $levels[max(0, $level - 1)], 0, $level, $msg];
        $rows[] = &$maps[$msg];
        $isleaf[$level - 1] = false;
        $level++;
    ## 步出时
    } else {
        $level--;
        $maps[$msg][2] = $now - $maps[$msg][0];
        if (!empty($isleaf[$level])) {
            $rank[] = sprintf("%.5f", $maps[$msg][2]);
        }
    }

    if ($output) {
        rsort($rank);
        $rank = array_flip($rank);
        foreach ($rows as &$r) {
            $indent = str_repeat('-   ', $r[3]);
            $order = $rank[sprintf("%.5f", $r[2])];
            $r = sprintf("%8.5f %8.5f %8.6f %2s %s%s", $r[0], $r[1], $r[2], $order < 5 ? $order : ' ', $indent, $r[4]);
        }
        echo "\n\n<!--\n" . implode("\n", $rows) . "\n -->";
        global $wpdb;
        echo "\n\n<!--\n";
        print_r(count($wpdb->queries));

        if (function_exists('opcache_get_configuration')) {
            ### opcache 的动态设定不管用，需在 .ini 文件中配置
            ## 是否保存文件/函数的注释
            // ini_set('opcache.save_comments', 0);
            ## 打开快速关闭, 打开这个在PHP Request Shutdown的时候会收内存的速度会提高
            // ini_set('opcache.fast_shutdown', 1);
            // ini_set('opcache.memory_consumption', 128 * 1024 * 1024);
            // ini_set('opcache.max_accelerated_files', 5000);
            print_r(opcache_get_configuration());
            $opcache_status = opcache_get_status();
            $opcache_status['scripts'] = count($opcache_status['scripts']);
            print_r($opcache_status);
            echo "\n -->";
        }
    }
}
?>
