<?php


function main($argv)
{
    if (count($argv) <= 1) {
        return print_help();
    }

    array_shift($argv);

    $type = array_shift($argv);

    if (in_array(strtolower($type), ['-h', '--help', '-?'])) {
        return print_help();
    }

    switch ($type) {
        case '-e':
            $isEncode = true;
            break;
        case '-d':
            $isEncode = false;
            break;
        default:
            return print_help();
    }

    $inputFile = 'php://input';
    if (count($argv) > 0) {
        $inputFile = array_shift($argv);
        if ($inputFile == '-') {
            $inputFile = 'php://input';
        }
    }

    $outputFile = 'php://output';
    if (count($argv) > 0) {
        $outputType = array_shift($argv);
        if ($outputType != '-o') {
            return print_help();
        }

        $outputFile = array_shift($argv);
        if ($outputFile == '-') {
            $outputFile = 'php://output';
        }
    }

    if (!empty($argv)) {
        return print_help();
    }

    $passwd = prompt_silent('Please enter your password: ');

    $inputFileHandle = fopen($inputFile, 'rb');
    if (!$inputFileHandle) {
        fprintf(STDERR, "Error: Invalid input file: %s.\n", $inputFile);
        return 2;
    }

    $inputFileBlocks = [];
    while (!feof($inputFileHandle)) {
        $inputFileBlocks[] = fread($inputFileHandle, 4096);
    }

    fclose($inputFileHandle);

    $inputFileContent = implode('', $inputFileBlocks);

    $beginTime = microtime(true);
    fprintf(STDERR, "Begin %s... ", $isEncode ? 'encrypting' : 'decrypting');

    // todo: 解析命令行，让这些算法和模式也从命令行输入
    $config = [
        'cipher' => MCRYPT_RIJNDAEL_256,
        'mode' => 'cbc',  // "ecb", "cbc", "cfb", "ofb", "nofb" or "stream"
        'rand' => MCRYPT_DEV_URANDOM,
        'magic' => 'CRY',
    ];

    echo "Input len: " . strlen($inputFileContent) . PHP_EOL;

    try {
        if ($isEncode) {
            $outputFileContent = do_encode($inputFileContent, $passwd, $config);
        } else {
            $outputFileContent = do_decode($inputFileContent, $passwd, $config);
        }
    } catch (\Exception $e) {
        fprintf(STDERR, "Error: %s.\n", $e->getMessage());
        return $e->getCode() ?: 99;
    }

    fprintf(STDERR, "Done. Total %.3fs.\n", microtime(true) - $beginTime);

    $outputFileHandle = fopen($outputFile, 'wb');
    if (!$outputFileHandle) {
        fprintf(STDERR, "Error: Invalid output file: %s.\n", $outputFile);
        return 2;
    }

    $beginTime = microtime(true);
    fprintf(STDERR, "Begin write to output file... ");

    $result = fwrite($outputFileHandle, $outputFileContent);
    if ($result === false) {
        fclose($outputFileHandle);
        fprintf(STDERR, "Error: Cannot write to output file: %s.\n", $outputFile);
        return 2;
    }

    fflush($outputFileHandle);
    fclose($outputFileHandle);

    fprintf(STDERR, "Done. Total %.3fs.\n", microtime(true) - $beginTime);

    return 0;
}


function print_help()
{
    // [ -A AES128|AES192|AES256|DES|3DES ] [ -M ECB|CBC|CFB|OFB|NOFB|STREAM ]
    echo <<<HELP
cryptor -e <input-file> [ -o <output-file> ]
cryptor -d <input-file> [ -o <output-file> ]

HELP;
    return 1;
}

function do_encode($data, $passwd, $config)
{
    $ivSize = mcrypt_get_iv_size($config['cipher'], $config['mode']);
    $keySize = mcrypt_get_key_size($config['cipher'], $config['mode']);

    $key = substr(str_pad($passwd, $keySize, "\0", STR_PAD_RIGHT), 0, $keySize);
    $iv = mcrypt_create_iv($ivSize, $config['rand']);

    $cipherData = mcrypt_encrypt($config['cipher'], $key, pack('N', strlen($data)) . $data, $config['mode'], $iv);
    $cipherData = $config['magic'] . $iv . $cipherData;
    return $cipherData;
}

function do_decode($data, $passwd, $config)
{
    $ivSize = mcrypt_get_iv_size($config['cipher'], $config['mode']);
    $keySize = mcrypt_get_key_size($config['cipher'], $config['mode']);

    $key = substr(str_pad($passwd, $keySize, "\0", STR_PAD_RIGHT), 0, $keySize);
    $magicLen = strlen($config['magic']);

    if (strlen($data) < $magicLen + $ivSize) {
        throw new \LogicException("Data to short to decrypt!");
    }


    if ($magicLen > 0 && substr_compare($data, $config['magic'], 0, $magicLen) !== 0) {
        throw new \LogicException("Invalid data to decrypt!");
    }

    $iv = substr($data, $magicLen, $ivSize);
    $cipherData = substr($data, $magicLen + $ivSize);
    $plainData = mcrypt_decrypt($config['cipher'], $key, $cipherData, $config['mode'], $iv);

    $unpackedLenArr = unpack('N', substr($plainData, 0, 4));
    $plainDataLen = reset($unpackedLenArr);
    return substr($plainData, 4, $plainDataLen);
}

/**
 * Read something from user input silently -- for password
 *
 * @param string $prompt
 * @return string|null
 */
function prompt_silent($prompt = "Enter Password:")
{
    if ('\\' === DIRECTORY_SEPARATOR) {
        $exe = __DIR__ . '/resources/hiddeninput.exe';

        // handle code running from a phar
        if ('phar:' === substr(__FILE__, 0, 5)) {
            $tmpExe = sys_get_temp_dir() . '/hiddeninput.exe';
            copy($exe, $tmpExe);
            $exe = $tmpExe;
        }

        $value = rtrim(shell_exec($exe));

        if (isset($tmpExe)) {
            unlink($tmpExe);
        }

        return $value;
    } else {
        $command = "/usr/bin/env bash -c 'echo OK'";
        if (rtrim(shell_exec($command)) !== 'OK') {
            trigger_error("Can't invoke bash");
            return null;
        }

        $command = "/usr/bin/env bash -c 'read -s -p \""
            . addslashes($prompt)
            . "\" mypassword && echo \$mypassword'";
        $password = rtrim(shell_exec($command));
        echo "\n";
        return $password;
    }
}

exit(main($argv));