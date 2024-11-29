<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

define('LOGIN_URL', 'http://34.203.73.5:7000/login');
define('VALIDATION_URL', 'http://34.203.73.5:7000/aws/face/compares');

// Credenciais de login
define('USERNAME', 'cafe_ead');
define('PASSWORD', "d3s4N8O'08w)");

// Função para obter o token de autenticação
function getAuthToken()
{
    $client = new Client();

    try {
        $response = $client->post(LOGIN_URL, [
            'headers' => ['Accept' => 'application/json'],
            'json' => [
                'usuario' => USERNAME,
                'senha' => PASSWORD
            ]
        ]);

        $data = json_decode($response->getBody(), true);

        if (isset($data['token'])) {
            return $data['token'];
        } else {
            throw new Exception('Token não encontrado na resposta.');
        }
    } catch (RequestException $e) {
        return ['error' => true, 'message' => $e->getMessage()];
    }
}

// Função para enviar as imagens para validação
function validateImages($firstImageBase64, $secondImageBase64, $authToken)
{
    $client = new Client();

    try {
        $response = $client->post(VALIDATION_URL, [
            'headers' => [
                'Accept' => '*/*',
                'Authorization' => 'Bearer ' . $authToken,
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'firstImage' => $firstImageBase64,
                'secondImage' => $secondImageBase64
            ]
        ]);

        return json_decode($response->getBody(), true);
    } catch (RequestException $e) {
        return ['error' => true, 'message' => $e->getMessage()];
    }
}

// Receber dados do frontend
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['firstImage']) || !isset($data['secondImage'])) {
    echo json_encode(['success' => false, 'message' => 'Imagens não fornecidas.']);
    exit;
}

// Obter o token de autenticação
$authResponse = getAuthToken();

if (isset($authResponse['error'])) {
    echo json_encode(['success' => false, 'message' => 'Erro ao autenticar.', 'details' => $authResponse['message']]);
    exit;
}

$authToken = $authResponse;

// Validar as imagens
$validationResponse = validateImages($data['firstImage'], $data['secondImage'], $authToken);

if (isset($validationResponse['error'])) {
    echo json_encode(['success' => false, 'message' => 'Erro ao validar imagens.', 'details' => $validationResponse['message']]);
    exit;
}

// Retornar o resultado da validação
echo json_encode(['success' => true, 'data' => $validationResponse]);
