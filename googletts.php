#!/usr/bin/env php
<?php

// Chaves e configurações da API do Google
$tts_apikey = "SUA_CHAVE_API"; // Substitua pela sua chave de API do Google
$tts_url = "https://texttospeech.googleapis.com/v1/text:synthesize?key=$tts_apikey";  // URL da API do Google TTS
$tts_voice = "pt-BR-Standard-B"; // Voz do Google TTS
$tts_language = "pt-BR";  // Linguagem desejada

header('Content-Type: text/html; charset=utf-8');

include_once "phpagi.php";
$dir = "/var/lib/asterisk/sounds/tts/";
if( !is_dir($dir) ) mkdir($dir, 0775);

$agi = new AGI();
$text = utf8_encode($argv[1]); // Codifica o texto corretamente
$file = "google-".md5($text);
$dir_file = $dir.$file;
$tmp_file = "/tmp/$file.wav";
$txt_file = "$dir_file.txt";

$agi->verbose("Início do Google TTS AGI.");

if (!isset($text)) {
    $agi->verbose("Texto vazio :(");
    return 0;
}

if (file_exists("$dir_file.wav") || file_exists("$dir_file.sln")) {
    $agi->verbose("Arquivo $file existente.");
    $agi->stream_file($dir_file,"#");
    return 0;
}

// Realizando a requisição para a API Google Text-to-Speech
$agi->verbose("Tentando contato com Google Text-to-Speech API.");
$response = generate_tts_audio($text, $tts_voice, $tts_language);

if ($response['error']) {
    $agi->verbose("Erro ao gerar áudio: " . $response['error']);
    return 0;
}

// Salva o áudio recebido como arquivo WAV temporário
file_put_contents($tmp_file, $response['audio_content']);

if(file_exists($tmp_file)) {
    // Converte o arquivo WAV para os formatos exigidos pelo Asterisk
    exec("sox --ignore-length $tmp_file -q -r 8000 -c 1 $dir_file.wav");
    exec("sox --ignore-length $tmp_file -q -r 8000 -t raw $dir_file.sln");
    $fp = fopen($txt_file, "w");
    fwrite($fp, $text);
    fclose($fp);
    unlink($tmp_file);
} else {
    $agi->verbose("Erro: Arquivo $tmp_file não existe");
    return 0;
}

if (file_exists("$dir_file.wav") || file_exists("$dir_file.sln")) {
    $agi->verbose("Arquivo $file foi gerado");
    $agi->wait_for_digit(1000);
    $agi->stream_file($dir_file,"#");
} else {
    $agi->verbose("Erro: Arquivo $file não existe.");
    return 0;
}

// Função para realizar a requisição à API Google TTS
function generate_tts_audio($text, $voice, $language) {
    // Configuração do JSON de requisição
    $request_payload = json_encode([
        'input' => ['text' => $text],
        'voice' => ['languageCode' => $language, 'name' => $voice],
        'audioConfig' => ['audioEncoding' => 'LINEAR16']
    ]);

    // Chamada CURL para o Google TTS
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://texttospeech.googleapis.com/v1/text:synthesize?key=$GLOBALS[tts_apikey]");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request_payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    // Executa a requisição e captura a resposta
    $result = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Caso haja erro na CURL ou na API
    if ($curl_error) {
        return ['error' => 'CURL error: ' . $curl_error];
    }

    $response = json_decode($result, true);

    // Verifica se houve erro na resposta da API
    if (isset($response['error'])) {
        return ['error' => $response['error']['message']];
    }

    return [
        'error' => null,
        'audio_content' => base64_decode($response['audioContent'])  // Decodifica o áudio base64
    ];
}
?>
