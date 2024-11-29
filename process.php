<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Intervention\Image\ImageManagerStatic as Image;

const LOGIN_URL = 'http://34.203.73.5:7000/login';
const VALIDATION_URL = 'http://34.203.73.5:7000/serpro/face/compares';

// Credenciais de login
const USERNAME = 'cafe_ead';
const PASSWORD = "d3s4N8O'08w)";

// Função para obter o token de autenticação
function getAuthToken()
{
    $client = new Client(); // Desativa verificação SSL em ambiente de desenvolvimento

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
        $response = $e->getResponse();
        $errorDetails = $response ? $response->getBody()->getContents() : 'Sem detalhes adicionais.';
        return ['error' => true, 'message' => $e->getMessage(), 'details' => $errorDetails, 'cpf' => $_POST['cpf'], 'image' => $_POST['image']];
    }
}

// Função para validar a imagem via API
function validateImage($cpf, $imageBase64, $authToken)
{
    $client = new Client(['verify' => false]); // Desativa verificação SSL em ambiente de desenvolvimento

    try {
        $response = $client->post(VALIDATION_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $authToken,
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'cpf' => $cpf,
                'validacao' => [
                    'biometria_facial' => [
                        'vivacidade' => true,
                        'formato' => 'PNG',
                        'base64' => $imageBase64
                    ]
                ]
            ]
        ]);

        return json_decode($response->getBody(), true);
    } catch (RequestException $e) {
        $response = $e->getResponse();
        $errorDetails = $response ? $response->getBody()->getContents() : 'Sem detalhes adicionais.';
        return ['error' => true, 'message' => $e->getMessage(), 'details' => $errorDetails, 'cpf' => $cpf, 'image' => $imageBase64];
    }
}

// Função para redimensionar e validar a imagem
function processImage($imageBase64)
{
    // Decodificar imagem Base64
    $imageData = base64_decode($imageBase64);
    $image = Image::make($imageData);

    // Verificar resolução mínima
    if ($image->width() < 250 || $image->height() < 250) {
        throw new Exception('A resolução mínima da imagem deve ser 250 x 250 pixels.');
    }

    // Redimensionar para 750 x 750 pixels (se necessário)
    if ($image->width() > 750 || $image->height() > 750) {
        $image->resize(750, 750, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
    }

    // Garantir que a imagem esteja no formato PNG
    $image->encode('png');

    // Verificar tamanho do arquivo
    if (strlen((string) $image) > 3 * 1024 * 1024) { // 3MB
        throw new Exception('O tamanho da imagem não pode exceder 3 MB.');
    }

    // Retornar imagem convertida para Base64
    return base64_encode((string) $image);
}

// Receber dados do frontend
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['cpf']) || !isset($data['image'])) {
    echo json_encode(['success' => false, 'message' => 'CPF ou imagem não fornecidos.']);
    exit;
}

// Validar CPF
$cpf = $data['cpf'];
if (!preg_match('/^\d{11}$/', $cpf)) {
    echo json_encode(['success' => false, 'message' => 'CPF inválido. Certifique-se de que ele contém apenas números e 11 dígitos.']);
    exit;
}

// Processar e validar a imagem
try {
    $processedImageBase64 = processImage($data['image']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// Obter o token de autenticação
try {
    $authResponse = getAuthToken();
} catch (Exception $e) {
}

if (isset($authResponse['error'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao autenticar na API.',
        'details' => $authResponse['details']
    ]);
    exit;
}

$authToken = $authResponse;

// Validar a imagem
$validationResponse = validateImage($cpf, $processedImageBase64, $authToken);

if (isset($validationResponse['error'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao validar a imagem.',
        'details' => $validationResponse['details']
    ]);
    exit;
}

// Retornar o resultado da validação
echo json_encode(['success' => true, 'data' => $validationResponse]);
