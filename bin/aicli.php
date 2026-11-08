<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Damoony\Ai\Navigator;
use Damoony\Ai\Roles;
use Damoony\Cli\Ai\AicliClient;
use Damoony\Cli\Ai\AicliHandlerInterface;
use Damoony\Cli\Ai\Handler\AgentChatHandler;
use Damoony\Cli\Ai\Handler\AiStartHandler;
use Damoony\Cli\Ai\Handler\AlliasListHandler;
use Damoony\Cli\Ai\Handler\AlliasNewHandler;
use Damoony\Cli\Ai\Handler\BlockListHandler;
use Damoony\Cli\Ai\Handler\BlockNewHandler;
use Damoony\Cli\Ai\Handler\BlockWriteHandler;
use Damoony\Cli\Ai\Handler\DataproviderAddMethodHandler;
use Damoony\Cli\Ai\Handler\DbCallHandler;
use Damoony\Cli\Ai\Handler\DbDescribeHandler;
use Damoony\Cli\Ai\Handler\DbMethodsHandler;
use Damoony\Cli\Ai\Handler\DbMutateHandler;
use Damoony\Cli\Ai\Handler\DbSchemaHandler;
use Damoony\Cli\Ai\Handler\DomainListHandler;
use Damoony\Cli\Ai\Handler\DomainNewHandler;
use Damoony\Cli\Ai\Handler\DoneHandler;
use Damoony\Cli\Ai\Handler\EngineStatusHandler;
use Damoony\Cli\Ai\Handler\FillerListHandler;
use Damoony\Cli\Ai\Handler\FillerNewHandler;
use Damoony\Cli\Ai\Handler\FillerWriteHandler;
use Damoony\Cli\Ai\Handler\NetHandler;
use Damoony\Cli\Ai\Handler\PageListHandler;
use Damoony\Cli\Ai\Handler\PageNewHandler;
use Damoony\Cli\Ai\Handler\PageReadHandler;
use Damoony\Cli\Ai\Handler\PageWriteHandler;
use Damoony\Cli\Ai\Handler\SchemeListHandler;
use Damoony\Cli\Ai\Handler\SchemeNewHandler;
use Damoony\Cli\Ai\Handler\SchemeSaveHandler;
use Damoony\Cli\Ai\Handler\SkinEditHandler;
use Damoony\Cli\Ai\Handler\SkinListHandler;
use Damoony\Cli\Ai\Handler\SkinNewHandler;
use Damoony\Cli\Ai\Handler\StructureCheckHandler;
use Damoony\Cli\Ai\Handler\StructureRepairHandler;
use Damoony\Cli\Ai\Handler\WizardHandler;
use Damoony\Cli\Ai\HandlerRegistry;

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
