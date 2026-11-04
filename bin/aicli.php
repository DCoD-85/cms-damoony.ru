<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Damoony\Ai\Aicli\AicliClient;
use Damoony\Ai\Aicli\AicliHandlerInterface;
use Damoony\Ai\Aicli\Handler\AgentChatHandler;
use Damoony\Ai\Aicli\Handler\AiStartHandler;
use Damoony\Ai\Aicli\Handler\AlliasListHandler;
use Damoony\Ai\Aicli\Handler\AlliasNewHandler;
use Damoony\Ai\Aicli\Handler\BlockListHandler;
use Damoony\Ai\Aicli\Handler\BlockNewHandler;
use Damoony\Ai\Aicli\Handler\BlockWriteHandler;
use Damoony\Ai\Aicli\Handler\DataproviderAddMethodHandler;
use Damoony\Ai\Aicli\Handler\DbCallHandler;
use Damoony\Ai\Aicli\Handler\DbDescribeHandler;
use Damoony\Ai\Aicli\Handler\DbMethodsHandler;
use Damoony\Ai\Aicli\Handler\DbMutateHandler;
use Damoony\Ai\Aicli\Handler\DbSchemaHandler;
use Damoony\Ai\Aicli\Handler\DomainListHandler;
use Damoony\Ai\Aicli\Handler\DomainNewHandler;
use Damoony\Ai\Aicli\Handler\DoneHandler;
use Damoony\Ai\Aicli\Handler\EngineStatusHandler;
use Damoony\Ai\Aicli\Handler\FillerListHandler;
use Damoony\Ai\Aicli\Handler\FillerNewHandler;
use Damoony\Ai\Aicli\Handler\FillerWriteHandler;
use Damoony\Ai\Aicli\Handler\NetHandler;
use Damoony\Ai\Aicli\Handler\PageListHandler;
use Damoony\Ai\Aicli\Handler\PageNewHandler;
use Damoony\Ai\Aicli\Handler\PageReadHandler;
use Damoony\Ai\Aicli\Handler\PageWriteHandler;
use Damoony\Ai\Aicli\Handler\SchemeListHandler;
use Damoony\Ai\Aicli\Handler\SchemeNewHandler;
use Damoony\Ai\Aicli\Handler\SchemeSaveHandler;
use Damoony\Ai\Aicli\Handler\SkinEditHandler;
use Damoony\Ai\Aicli\Handler\SkinListHandler;
use Damoony\Ai\Aicli\Handler\SkinNewHandler;
use Damoony\Ai\Aicli\Handler\StructureCheckHandler;
use Damoony\Ai\Aicli\Handler\StructureRepairHandler;
use Damoony\Ai\Aicli\Handler\WizardHandler;
use Damoony\Ai\Aicli\HandlerRegistry;
use Damoony\Ai\Navigator;
use Damoony\Ai\Roles;

/**
 * @param array<string, mixed> $response
 * @param array<string, mixed> $args
 */
function respond(array $response, string $role, ?string $cmd = null, array $args = []): never
{
    $response['role'] = $role;
    $response['next'] = [];

    if ($cmd === 'ai:start') {
        $state = $response['data']['state'] ?? ['domains' => []];
        if (!is_array($state)) {
            $state = ['domains' => []];
        }
        $next = Navigator::start($state);
    } elseif ($cmd !== null && $cmd !== 'done') {
        $next = Navigator::after($cmd, $args);
    } else {
        $next = [];
    }

    $whitelist = Roles::whitelist($role);
    $next = array_values(array_filter(
        $next,
        static fn (array $n): bool => $n['cmd'] === 'done' || in_array($n['cmd'], $whitelist, true),
    ));

    $response['next'] = $next;
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(($response['ok'] ?? false) === true ? 0 : 1);
}

// === Вход ===
$input = stream_get_contents(STDIN);
$input = $input === false ? '' : $input;
$request = json_decode($input, true);

if (!is_array($request) || !isset($request['command']) || !is_string($request['command'])) {
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON request', 'hint' => '{"command": "ai:start"}']);
    exit(1);
}

$command = $request['command'];
$args = is_array($request['args'] ?? null) ? $request['args'] : [];
$role = isset($request['role']) && is_string($request['role']) ? $request['role'] : '*';

if (!Roles::isValid($role)) {
    echo json_encode(['ok' => false, 'error' => "Unknown role '{$role}'", 'hint' => 'Allowed roles: * , ' . implode(', ', Roles::all())]);
    exit(1);
}

$allowedCommands = Roles::whitelist($role);

if (!in_array($command, $allowedCommands, true)) {
    respond([
        'ok' => false,
        'error' => "Command '{$command}' is not allowed for role '{$role}'.",
        'hint' => 'Allowed for this role: ' . implode(', ', $allowedCommands),
    ], $role, $command, $args);
}

// === Config ===
$root = __DIR__ . '/..';
$agentConfigFile = $root . '/config/agent.config';
$allowedDomains = [];
if (is_file($agentConfigFile)) {
    $agentRaw = file_get_contents($agentConfigFile);
    $agentConfig = $agentRaw === false ? null : json_decode($agentRaw, true);
    if (is_array($agentConfig) && isset($agentConfig['allowed_domains']) && is_array($agentConfig['allowed_domains'])) {
        /** @var list<string> $allowedDomains */
        $allowedDomains = array_values(array_filter(
            $agentConfig['allowed_domains'],
            static fn ($d): bool => is_string($d),
        ));
    }
}

// === Handlers ===
$client = new AicliClient($root);
$registry = new HandlerRegistry();

/** @var list<AicliHandlerInterface> $handlers */
$handlers = [
    new WizardHandler($client, $root, $allowedDomains),
    new NetHandler($client, $root, $allowedDomains),
    new AgentChatHandler($client, $root, $allowedDomains),
    new AiStartHandler($client, $root, $allowedDomains),
    new DoneHandler($client, $root, $allowedDomains),
    new EngineStatusHandler($client, $root, $allowedDomains),
    new StructureCheckHandler($client, $root, $allowedDomains),
    new StructureRepairHandler($client, $root, $allowedDomains),
    new DbSchemaHandler($client, $root, $allowedDomains),
    new DbMethodsHandler($client, $root, $allowedDomains),
    new DbDescribeHandler($client, $root, $allowedDomains),
    new DbCallHandler($client, $root, $allowedDomains),
    new DbMutateHandler($client, $root, $allowedDomains),
    new DataproviderAddMethodHandler($client, $root, $allowedDomains),
    new DomainListHandler($client, $root, $allowedDomains),
    new DomainNewHandler($client, $root, $allowedDomains),
    new AlliasListHandler($client, $root, $allowedDomains),
    new AlliasNewHandler($client, $root, $allowedDomains),
    new PageListHandler($client, $root, $allowedDomains),
    new PageNewHandler($client, $root, $allowedDomains),
    new PageReadHandler($client, $root, $allowedDomains),
    new PageWriteHandler($client, $root, $allowedDomains),
    new SchemeListHandler($client, $root, $allowedDomains),
    new SchemeNewHandler($client, $root, $allowedDomains),
    new SchemeSaveHandler($client, $root, $allowedDomains),
    new BlockListHandler($client, $root, $allowedDomains),
    new BlockNewHandler($client, $root, $allowedDomains),
    new BlockWriteHandler($client, $root, $allowedDomains),
    new FillerListHandler($client, $root, $allowedDomains),
    new FillerNewHandler($client, $root, $allowedDomains),
    new FillerWriteHandler($client, $root, $allowedDomains),
    new SkinListHandler($client, $root, $allowedDomains),
    new SkinNewHandler($client, $root, $allowedDomains),
    new SkinEditHandler($client, $root, $allowedDomains),
];
foreach ($handlers as $h) {
    $registry->add($h);
}

$handler = $registry->find($command);
if ($handler === null) {
    respond([
        'ok' => false,
        'error' => "Command '{$command}' not implemented.",
        'hint' => 'See ai:start for available commands',
    ], $role, $command, $args);
}

try {
    $result = $handler->handle($command, $args, $role);
    respond($result->toArray(), $role, $command, $args);
} catch (\Throwable $e) {
    respond([
        'ok' => false,
        'error' => $e->getMessage(),
        'hint' => 'Unexpected error',
    ], $role, $command, $args);
}
