<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

define('LOGIN_URL', 'http://34.203.73.5:7000/login');
define('VALIDATION_URL', 'https://gateway.apiserpro.serpro.gov.br/datavalid-demonstracao/v2/validate/pf-face');

// Credenciais de login
define('USERNAME', 'cafe_ead');
define('PASSWORD', "d3s4N8O'08w)");

// Função para obter o token de autenticação
function getAuthToken()
{
    $client = new GuzzleHttp\Client([
        'verify' => false, // Desativa a verificação SSL
    ]);



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

// Função para enviar a imagem para validação
function validateImage($cpf, $imageBase64, $authToken)
{
    $client = new Client();

    try {
        $response = $client->post(VALIDATION_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $authToken,
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'key' => [
                    'cpf' => $cpf
                ],
                'answer' => [
                    'biometria_face' => $imageBase64
                ]
            ]
        ]);

        return json_decode($response->getBody(), true);
    } catch (RequestException $e) {
        return ['error' => true, 'message' => $e->getMessage()];
    }
}

// Receber dados do frontend
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['cpf']) || !isset($data['image'])) {
    echo json_encode(['success' => false, 'message' => 'CPF ou imagem não fornecidos.']);
    exit;
}

// Obter o token de autenticação
$authResponse = getAuthToken();

if (isset($authResponse['error'])) {
    echo json_encode(['success' => false, 'message' => 'Erro ao autenticar.', 'details' => $authResponse['message']]);
    exit;
}

$authToken = $authResponse;

// Validar a imagem
$validationResponse = validateImage($data['cpf'], $data['image'], $authToken);

if (isset($validationResponse['error'])) {
    echo json_encode(['success' => false, 'message' => 'Erro ao validar imagem.', 'details' => $validationResponse['message']]);
    exit;
}

// Retornar o resultado da validação
echo json_encode(['success' => true, 'data' => $validationResponse]);
