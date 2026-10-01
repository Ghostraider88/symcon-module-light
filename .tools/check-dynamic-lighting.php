<?php

declare(strict_types=1);

// Standalone regression checks; no connection to Symcon and no device commands.
class IPSModuleStrict
{
    public int $InstanceID = 24129;
    public array $properties = [];
    public array $formUpdates = [];
    public array $buffers = [];
    public array $values = [];
    protected function GetBuffer(string $name): string { return $this->buffers[$name] ?? ''; }
    protected function SetBuffer(string $name, string $value): void { $this->buffers[$name] = $value; }
    protected function SetValue(string $name, mixed $value): void { $this->values[$name] = $value; }

    protected function ReadPropertyString(string $name): string
    {
        return (string)($this->properties[$name] ?? '[]');
    }

    protected function ReadPropertyInteger(string $name): int
    {
        return (int)($this->properties[$name] ?? 0);
    }

    protected function Translate(string $text): string
    {
        return $text;
    }

    protected function UpdateFormField(string $name, string $field, string $value): void
    {
        $this->formUpdates[$name][$field] = $value;
    }
}

$objects = [39774 => ['ObjectType' => 1, 'ObjectName' => 'Kitchen sink'], 41254 => ['ObjectType' => 1, 'ObjectName' => 'Beleuchtung']];
$variables = [];
$children = [39774 => [], 41254 => []];
foreach ([
    [36953, 'color_hs', '~HexColor', 1],
    [43668, 'color', '~HexColor', 1],
    [22267, 'color_temp_presets', 'Z2M.Color_Temp_125_666_Presets', 1],
    [59858, 'color_temp', 'Z2M.color_temp_125_666', 1],
    [30169, 'color_temp_kelvin', '~TWColor', 1],
    [57005, 'state', '~Switch', 0],
    [58879, 'brightness', '~Intensity.100', 1],
    [44208, 'device_status', 'Z2M.DeviceStatus', 0]
] as [$id, $ident, $profile, $type]) {
    $objects[$id] = ['ObjectType' => 2, 'ObjectIdent' => $ident, 'ParentID' => 39774];
    $variables[$id] = ['VariableType' => $type, 'VariableProfile' => $profile,
        'VariableCustomProfile' => '', 'VariableAction' => $id === 44208 ? 0 : 39774, 'VariableCustomAction' => 0];
    $children[39774][] = $id;
}
foreach ([49677 => ['Scene1', 'AUS'], 55736 => ['Scene2', 'Ambiente'], 57802 => ['Scene3', 'Abendessen'],
    25853 => ['Scene4', 'Party'], 45470 => ['Scene5', 'Fernsehen'], 44543 => ['ActiveScene', 'Active scene']] as $id => [$ident, $name]) {
    $objects[$id] = ['ObjectType' => 2, 'ObjectIdent' => $ident, 'ObjectName' => $name, 'ParentID' => 41254];
    $children[41254][] = $id;
}
function IPS_GetObject(int $id): array { return $GLOBALS['objects'][$id]; }
function IPS_GetChildrenIDs(int $id): array { return $GLOBALS['children'][$id] ?? []; }
function IPS_GetVariable(int $id): array { return $GLOBALS['variables'][$id]; }
function IPS_VariableExists(int $id): bool { return isset($GLOBALS['variables'][$id]); }
function IPS_InstanceExists(int $id): bool { return isset($GLOBALS['objects'][$id]) && $GLOBALS['objects'][$id]['ObjectType'] === 1; }
function IPS_GetInstance(int $id): array { return ['ModuleInfo' => ['ModuleName' => $id === 41254 ? 'SceneControl' : 'Light']]; }
function IPS_GetInstanceListByModuleID(string $moduleID): array { return $moduleID === '{87F46796-CC43-442D-94FD-AAA0BD8D9F54}' ? [41254] : []; }
function GetValue(int $id): mixed { return $id === 43668 ? 16711680 : 0; }

require __DIR__ . '/../DynamicLighting/module.php';
$module = new DynamicLighting();
$module->properties = [
    'Profiles' => json_encode([['ProfileID' => 'summer-profile', 'Name' => 'Summer']], JSON_THROW_ON_ERROR),
    'TargetProfiles' => json_encode([['TargetName' => 'Sink', 'ProfileID' => 'summer-profile', 'ColorValue' => '#FF0000', 'Temperature' => 0]], JSON_THROW_ON_ERROR),
    'Targets' => json_encode([['Name' => 'Sink', 'SwitchID' => 57005, 'ColorID' => 43668, 'MaxBrightness' => 42]], JSON_THROW_ON_ERROR),
    'SceneControlID' => 41254, 'OffScene' => 1, 'TVScene' => 5
];
$checks = 0;
function check(bool $condition, string $description): void
{
    if (!$condition) throw new RuntimeException($description);
    $GLOBALS['checks']++;
}
function field(array $elements, string $name): ?array
{
    foreach ($elements as $element) {
        if (($element['name'] ?? '') === $name) return $element;
        $found = field($element['items'] ?? [], $name);
        if ($found !== null) return $found;
    }
    return null;
}

$scan = new ReflectionMethod(DynamicLighting::class, 'ScanLightInstance');
$light = $scan->invoke($module, 39774);
check($light['SwitchID'] === 57005, 'Switch detection');
check($light['BrightnessID'] === 58879, 'Brightness detection');
check($light['ColorID'] === 43668, 'Native color must outrank color_hs');
check($light['TemperatureID'] === 30169, 'Kelvin must outrank Mired and presets');

$module->DiscoverLights(39774);
$targets = json_decode($module->formUpdates['Targets']['values'], true, 512, JSON_THROW_ON_ERROR);
check(count($targets) === 1, 'Discovery must refresh an existing light without duplication');
check($targets[0]['Name'] === 'Sink' && $targets[0]['MaxBrightness'] === 42, 'Discovery must preserve user settings');
check($targets[0]['TemperatureID'] === 30169, 'Discovery must refresh Kelvin mapping');
$targets[0]['MaxBrightness'] = 55;
$targets[] = ['Name' => 'Another unsaved light', 'SwitchID' => 0, 'ColorID' => 0];
$module->DiscoverLights(39774, json_encode($targets, JSON_THROW_ON_ERROR));
$pendingTargets = json_decode($module->formUpdates['Targets']['values'], true, 512, JSON_THROW_ON_ERROR);
check(count($pendingTargets) === 2 && $pendingTargets[0]['MaxBrightness'] === 55, 'Discovery must preserve other unsaved rows and edits');
$module->properties['Targets'] = '[]';
$module->DiscoverLights(39774);
$targets = json_decode($module->formUpdates['Targets']['values'], true, 512, JSON_THROW_ON_ERROR);
check(count($targets) === 1 && $targets[0]['Name'] === 'Kitchen sink', 'Discovery must add the selected instance');
$module->properties['Targets'] = json_encode([array_replace($light, ['Name' => 'Sink'])], JSON_THROW_ON_ERROR);

$module->CaptureCurrentColor('Sink', 'summer-profile', '{"Temperature":4000}');
$settings = json_decode($module->formUpdates['TargetProfiles']['values'], true, 512, JSON_THROW_ON_ERROR);
check($settings[0]['ColorValue'] === 16711680, 'Captured red must be numeric RGB, not a CSS string');
check($settings[0]['Temperature'] === 4000, 'Capture must preserve the edited row temperature');
$settings[] = ['TargetName' => 'Another light', 'ProfileID' => 'summer-profile', 'ColorValue' => 255, 'Temperature' => 0];
$module->CaptureCurrentColor('Sink', 'summer-profile', json_encode($settings, JSON_THROW_ON_ERROR));
$pendingSettings = json_decode($module->formUpdates['TargetProfiles']['values'], true, 512, JSON_THROW_ON_ERROR);
check(count($pendingSettings) === 2 && $pendingSettings[1]['ColorValue'] === 255, 'Capture must preserve other unsaved profile rows');
check($pendingSettings[0]['Temperature'] === 4000, 'Capture from a table snapshot must preserve selected row edits');

$form = json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
$profileField = field($form['elements'], 'TargetProfiles');
check($profileField['loadValuesFromConfiguration'] === false, 'Normalized values must take precedence over stored CSS strings');
check($profileField['values'][0]['ColorValue'] === 16711680, 'Stored CSS color must display as numeric RGB');
$targetField = field($form['elements'], 'Targets');
check(count(array_filter($targetField['columns'], static fn (array $column): bool => preg_match('/^(Spring|Summer|Autumn|Winter)(Color|Temperature)$/', $column['name']) === 1)) === 0, 'Legacy seasonal fields must not appear in the light editor');
check(field($form['elements'], 'DiscoveryInstanceID')['type'] === 'SelectInstance', 'Discovery must select an instance');
check(field($form['elements'], 'TVScene')['options'][5]['caption'] === 'Fernsehen', 'TV selector must use actual scene names');
$module->SelectSceneControl(41254);
check($module->formUpdates['ActiveSceneID']['value'] === '44543', 'Scene selection must discover ActiveScene by ident');
check(field($form['elements'], 'SceneControlID')['type'] === 'SelectInstance', 'Scene Control must use the requested instance selector');
check(!isset(field($form['elements'], 'SceneControlID')['onChange']), 'Scene loading must not update form fields while the instance picker is open');
check(field($form['elements'], 'ActiveSceneID')['validVariableTypes'] === [3], 'Active scene selection must require a string variable');
$module->properties['SceneControlID'] = 1;
$staleForm = json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
$controllerField = field($staleForm['elements'], 'SceneControlID');
check(field($staleForm['elements'], 'ResetSceneControlSelection')['visible'] === true, 'An invalid saved ID must offer a reset outside the object picker');
check($module->properties['SceneControlID'] === 1, 'Opening the form must not rewrite user properties');
$rejected = false;
try { $module->SelectSceneControl(1); } catch (InvalidArgumentException $exception) { $rejected = true; }
check($rejected, 'Stale scene controller callbacks must be rejected');
$module->SelectSceneControl(0);
check($module->formUpdates['ActiveSceneID']['value'] === '0', 'Disabling Scene Control must clear the suggested scene variable');
check($module->formUpdates['SceneControlID']['value'] === '0', 'Reset must clear the invalid controller in the form');
$module->SelectSceneControl(41254);
check($module->formUpdates['ActiveSceneID']['value'] === '44543', 'Valid selection must recover from an invalid saved controller');
$module->properties['SceneControlID'] = 41254;
$resolveScene = new ReflectionMethod(DynamicLighting::class, 'ActiveSceneNumber');
check($resolveScene->invoke($module, 'Abendessen') === 3, 'Resolve a scene name to its actual scene number');
check($resolveScene->invoke($module, 'Unbekannt') === null, 'Unknown scene must not be treated as a scene number');
check($resolveScene->invoke($module, '5') === null, 'Do not cast numeric-looking scene names to integers');
$handleScene = new ReflectionMethod(DynamicLighting::class, 'HandleActiveScene');
$module->values['Mode'] = 0;
$module->buffers['RequestedScene'] = '3';
$module->buffers['RequestedSceneUntil'] = (string)(time() + 30);
$handleScene->invoke($module, 'Unbekannt');
check($module->values['Mode'] === 0 && $module->buffers['RequestedScene'] === '3', 'An intermediate unknown scene must preserve pending confirmation');
$handleScene->invoke($module, 'Abendessen');
check($module->values['Mode'] === 0 && $module->buffers['RequestedScene'] === '', 'Own scene confirmation must not create a manual override');
$handleScene->invoke($module, 'Fernsehen');
check($module->values['Mode'] === 2, 'An externally selected TV scene must pause ambient control');
$module->values['Mode'] = 0;
$module->buffers['RequestedScene'] = '3';
$module->buffers['RequestedSceneUntil'] = (string)(time() - 1);
$handleScene->invoke($module, 'Abendessen');
check($module->values['Mode'] === 2, 'Expired command confirmation must not hide an external scene');
$variables[46236] = ['VariableType' => 2];
$variables[44543] = ['VariableType' => 3];
$module->properties += ['LuxVariableID' => 46236, 'ActiveSceneID' => 44543, 'ActiveProfile' => 'summer-profile',
    'BrightLux' => 300, 'DarkLux' => 2500, 'StartTime' => '16:00', 'EndTime' => '23:00', 'SceneTriggers' => '[]'];
$valid = new ReflectionMethod(DynamicLighting::class, 'ConfigurationIsValid');
check($valid->invoke($module) === true, 'A String ActiveScene must pass configuration validation');
$variables[44543]['VariableType'] = 1;
check($valid->invoke($module) === false, 'An Integer ActiveScene must fail configuration validation');

$convert = new ReflectionMethod(DynamicLighting::class, 'ColorToInteger');
foreach ([16711680, '#FF0000', '0xFF0000', 'FF0000', '16711680'] as $value) check($convert->invoke($module, $value) === 16711680, 'RGB input conversion');
foreach ([-1, '-1', null, '', 'invalid', 0x1000000] as $value) check($convert->invoke($module, $value) === null, 'Unset or invalid colors must not become black');
check($convert->invoke($module, 0) === 0, 'Explicit black remains a valid color');
echo $checks . " regression checks passed.\n";
