<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/NoteStore.php';

/**
 * Klebezettel, die frei über der Visualisierung liegen (über die „Visu-Erweiterung für Symcon“, an
 * deren Symcon-Modul diese Instanz andockt) und als Brett in einer Kachel. Die Zettel liegen im
 * Attribut `Notes`; Visu und Kachel lesen und schreiben über den Hook /hook/sticky/<ID>?t=<Token>.
 */
class StickyNotes extends IPSModuleStrict
{
    private const HOOK = 'sticky';
    // Andocken an das Modul „Visu-Erweiterung“ (Datenfluss): diese Instanz → Visu-Erweiterung und zurück
    private const DOCK_TX = '{A5B87602-1C34-4981-89A5-62A0735CB359}';
    private const DOCK_RX = '{9E7FA1CF-55F3-418B-8C92-FF4BAAC93EFA}';
    private const VISU_EXTENSION = '{CB9C6C38-9B97-45CA-BFB5-EC7F936AF3FF}';
    private const COLOR_NAMES = ['gelb' => 'yellow', 'rosa' => 'pink', 'grün' => 'green', 'gruen' => 'green', 'blau' => 'blue'];
    private const LABELS = ['New note', 'Show or hide notes', 'Type', 'Write by hand', 'Undo last stroke', 'Pen color', 'Note color',
        'Tear off', 'Tear off?', 'Done', 'The board is full', 'No notes', 'Note', 'Sticker'];
    // Sticker werden ueber den Hook ausgeliefert; nur diese Typen, kein SVG (es kann Skripte tragen)
    private const IMAGE_TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'bmp' => 'image/bmp'];
    // Der Hook liefert hoechstens 1 MiB aus; darueber ersetzt Symcon die Antwort
    private const MAX_STICKER_BYTES = 900000;
    // Eingebaute Sticker: Fluent Emoji 3D von Microsoft (MIT, imgs/fluent/LICENSE), 256 px. Name = Dateiname ohne .png.
    private const FLUENT_DIR = __DIR__ . '/../imgs/fluent/';
    private const FLUENT = [
        'red_heart' => 'Red heart', 'star' => 'Star', 'thumbs_up' => 'Thumbs up', 'check_mark_button' => 'Done mark',
        'warning' => 'Warning sign', 'red_exclamation_mark' => 'Exclamation mark', 'red_question_mark' => 'Question mark', 'hundred_points' => 'Hundred points',
        'sparkles' => 'Sparkles', 'fire' => 'Fire', 'clapping_hands' => 'Applause', 'party_popper' => 'Party',
        'smiling_face_with_heart_eyes' => 'In love', 'face_with_tears_of_joy' => 'Tears of joy', 'sleeping_face' => 'Sleeping', 'dog_face' => 'Dog',
        'cat_face' => 'Cat', 'house' => 'House', 'shopping_cart' => 'Shopping cart', 'light_bulb' => 'Idea',
        'key' => 'Key', 'alarm_clock' => 'Alarm clock', 'calendar' => 'Calendar', 'envelope' => 'Letter',
        'telephone_receiver' => 'Call', 'pushpin' => 'Pin', 'broom' => 'Broom', 'pill' => 'Pill',
        'bed' => 'Bed', 'pizza' => 'Pizza', 'hot_beverage' => 'Coffee', 'bread' => 'Bread',
        'glass_of_milk' => 'Milk', 'red_apple' => 'Apple', 'birthday_cake' => 'Birthday cake', 'wrapped_gift' => 'Gift',
        'balloon' => 'Balloon', 'sun' => 'Sun', 'umbrella' => 'Umbrella', 'snowflake' => 'Snowflake',
    ];
    private const JSON = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyBoolean('Overlay', true);
        $this->RegisterPropertyInteger('Corner', 3);
        $this->RegisterPropertyInteger('PollSeconds', 3);
        $this->RegisterPropertyString('Stickers', '[]');
        $this->RegisterPropertyBoolean('BuiltinStickers', true);
        $this->RegisterAttributeString('Notes', '[]');
        $this->RegisterAttributeInteger('Seq', 0);
        $this->RegisterAttributeString('HookToken', '');

        $this->RegisterVariableInteger('Count', $this->Translate('Notes'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON'         => 'note-sticky',
        ], 10);

        $this->SetBuffer('Hook', $this->RegisterHook(self::HOOK . '/' . $this->InstanceID) ? '1' : '');
        $this->SetVisualizationType(defined('INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN') ? INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN : 1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        // Von der Visu-Erweiterung nur Pakete an alle (Target 0) oder an diese Instanz
        $this->SetReceiveDataFilter('.*"Target":(0|' . $this->InstanceID . ')[,}].*');
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }
        if ($this->ReadAttributeString('HookToken') === '') {
            $this->WriteAttributeString('HookToken', bin2hex(random_bytes(16)));
        }
        $this->SetValue('Count', count($this->Load()));
        $this->UpdateVisualizationValue((string) json_encode(['type' => 'reload']));
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === IPS_KERNELSTARTED) {
            $this->ApplyChanges();
        }
    }

    public function Destroy(): void
    {
        parent::Destroy();
    }

    // ── Skriptfunktionen ──

    /** Klebt einen Zettel an; liefert seine Kennung oder '' (Brett voll, unbekannte Farbe). */
    public function Add(string $Text, string $Color): string
    {
        $color = strtolower(trim($Color));
        $color = self::COLOR_NAMES[$color] ?? ($color === '' ? 'yellow' : $color);
        return (string) $this->Mutate(static function (array $list) use ($Text, $color): array {
            $note = NoteStore::Normalize(NoteStore::ScriptNote($list, $Text, $color));
            if (is_string($note)) {
                return [$list, ''];
            }
            [$next, $error] = NoteStore::Put($list, $note, time());
            return $error === null ? [$next, $note['id']] : [$list, ''];
        });
    }

    public function Remove(string $NoteID): bool
    {
        return (bool) $this->Mutate(static function (array $list) use ($NoteID): array {
            $next = NoteStore::Remove($list, $NoteID);
            return [$next, count($next) !== count($list)];
        });
    }

    public function Clear(): void
    {
        $this->Mutate(static fn (array $list): array => [[], null]);
    }

    /** Alle Zettel als JSON (ohne Handschrift-Linien, dafür mit `ink` = Zahl der Striche). */
    public function GetNotes(): string
    {
        return (string) json_encode(array_map(static fn (array $n): array => [
            'id' => $n['id'], 'text' => $n['text'], 'color' => $n['color'], 'ink' => count($n['strokes']), 'ts' => (int) ($n['ts'] ?? 0),
        ], $this->Load()), self::JSON);
    }

    // ── Kachel und Formular ──

    public function GetVisualizationTile(): string
    {
        return strtr((string) file_get_contents(__DIR__ . '/module.html'), ['/*STICKY_JS*/' => $this->Script('tile')]);
    }

    public function GetConfigurationForm(): string
    {
        $form = json_decode((string) file_get_contents(__DIR__ . '/form.json'), true);
        // Ein Sticker, der nicht ausgeliefert werden kann, taucht gar nicht erst in der Auswahl auf: hier steht warum
        $problems = [];
        foreach (json_decode($this->ReadPropertyString('Stickers'), true) ?: [] as $row) {
            $id = is_array($row) ? (int) ($row['Media'] ?? 0) : 0;
            $why = $id > 0 ? $this->StickerProblem($id) : '';
            if ($why !== '') {
                $problems[] = ($id > 0 && IPS_MediaExists($id) ? IPS_GetName($id) : '#' . $id) . ': ' . $this->Translate($why);
            }
        }
        if ($problems !== []) {
            array_unshift($form['elements'], ['type' => 'Label', 'caption' => implode("\n", array_unique($problems))]);
        }
        // Die Browser-Erweiterung läuft über die Instanz „Visu-Erweiterung“, an die diese Instanz andockt
        $form['actions'][] = ['type' => 'Label', 'caption' => $this->Docked()
            ? 'This instance is docked onto the Visu Extension: browsers paired there show the notes on every page.'
            : 'This instance is not connected to an instance "Visu Extension" yet (change gateway).'];
        return (string) json_encode($form, self::JSON);
    }

    // ── Andocken an die Visu-Erweiterung ──

    /** An eine vorhandene Instanz „Visu-Erweiterung“ anschließen; ohne sie bringt die Kachel die Zettel in die Visu. */
    public function GetCompatibleParents(): string
    {
        return (string) json_encode(['type' => 'connect', 'moduleIDs' => [self::VISU_EXTENSION]]);
    }

    /**
     * Anfragen der Visu-Erweiterung; die Rückgabe ist die Antwort.
     *   Describe  → {instance, name, version, active, info}; aktiv nur mit „Zettel über der Visu“
     *   Script    → das Zettel-Skript fürs Fenster der Visu (wie k=agent.js)
     */
    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        if (!is_array($data) || ($data['DataID'] ?? '') !== self::DOCK_RX || !in_array($data['Target'] ?? null, [0, $this->InstanceID], true)) {
            return '';
        }
        $overlay = $this->ReadPropertyBoolean('Overlay') && $this->HookBase() !== '';
        switch ((string) ($data['Action'] ?? '')) {
            case 'Describe':
                return (string) json_encode([
                    'instance' => $this->InstanceID,
                    'name'     => IPS_GetName($this->InstanceID),
                    'version'  => $this->LibraryVersion(),
                    'active'   => $overlay,
                    'info'     => $overlay ? '' : $this->Translate('"Show notes on top of the visualization" is switched off.'),
                ], self::JSON);
            case 'Script':
                return $overlay ? $this->Script('overlay') : '';
        }
        return '';
    }

    /** Sagt den Browsern an der Visu-Erweiterung, dass sich die Zettel geändert haben: Sie fragen dann sofort statt im Takt. */
    private function Nudge(): void
    {
        if (!$this->ReadPropertyBoolean('Overlay') || !$this->Docked() || !$this->HasActiveParent()) {
            return;
        }
        try {
            $this->SendDataToParent((string) json_encode([
                'DataID'   => self::DOCK_TX,
                'Action'   => 'Send',
                'Instance' => $this->InstanceID,
                'Device'   => '',
                'Data'     => ['seq' => $this->ReadAttributeInteger('Seq')],
            ]));
        } catch (\Throwable $e) {
            $this->SendDebug('Dock', $e->getMessage(), 0);
        }
    }

    /** Hängt diese Instanz im Datenfluss an einer übergeordneten Instanz? */
    protected function Docked(): bool
    {
        return (int) (@IPS_GetInstance($this->InstanceID)['ConnectionID'] ?? 0) > 0;
    }

    protected function LibraryVersion(): string
    {
        try {
            $module = IPS_GetModule(IPS_GetInstance($this->InstanceID)['ModuleInfo']['ModuleID']);
            return (string) (IPS_GetLibrary($module['LibraryID'])['Version'] ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * /hook/sticky/<ID>?t=<Token>&k=…
     *   k=agent.js            Zettel-Skript für das Fenster der Visu (nur mit „Zettel über der Visu“); lädt die Kachel, wenn
     *                         keine Visu-Erweiterung es bringt (die bekommt es über ReceiveData)
     *   k=poll&since=<seq>    Stand; die Zettel nur, wenn sich seit <seq> etwas geändert hat
     *   k=save  (POST, JSON)  {op: put, note, part?: place|content} | {op: del, id}; antwortet mit dem Stand
     *   k=img&m=<MedienID>    Bild eines eingerichteten Stickers; alles andere bleibt unerreichbar
     */
    protected function ProcessHookData(): void
    {
        $token = $this->ReadAttributeString('HookToken');
        $given = isset($_GET['t']) && is_string($_GET['t']) ? $_GET['t'] : '';
        if ($token === '' || !hash_equals($token, $given)) {
            $this->SendStatus(403);
            return;
        }
        $key = isset($_GET['k']) && is_string($_GET['k']) ? $_GET['k'] : '';
        $this->SendHeader('X-Content-Type-Options: nosniff');
        // Sticker aendern sich selten und kosten sonst bei jedem Bild einen Abruf
        // Eingebaute Bilder aendern sich nie (fester Dateiname), eigene Medien selten
        $this->SendHeader($key === 'img' ? 'Cache-Control: private, max-age=' . (isset($_GET['e']) ? 86400 : 300) : 'Cache-Control: no-store');
        $overlay = $this->ReadPropertyBoolean('Overlay');
        switch ($key) {
            case 'agent.js':
                if (!$overlay) {
                    $this->SendStatus(404);
                    return;
                }
                $this->SendHeader('Content-Type: application/javascript; charset=utf-8');
                echo $this->Script('overlay');
                return;
            case 'poll':
                $since = isset($_GET['since']) && is_numeric($_GET['since']) ? (int) $_GET['since'] : -1;
                $this->SendHeader('Content-Type: application/json; charset=utf-8');
                echo json_encode($this->State($since), self::JSON);
                return;
            case 'img':
                $builtin = isset($_GET['e']) && is_string($_GET['e']) ? $_GET['e'] : '';
                if ($builtin !== '') {
                    if (!$this->IsBuiltin($builtin)) {
                        $this->SendStatus(404);
                        return;
                    }
                    $this->SendHeader('Content-Type: image/png');
                    echo (string) file_get_contents(self::FLUENT_DIR . $builtin . '.png');
                    return;
                }
                $id = isset($_GET['m']) && is_numeric($_GET['m']) ? (int) $_GET['m'] : 0;
                $type = $this->IsSticker($id) ? $this->StickerType($id) : '';
                $bytes = $type === '' ? '' : (string) base64_decode(IPS_GetMediaContent($id), true);
                if ($type === '' || $bytes === '' || strlen($bytes) > self::MAX_STICKER_BYTES) {
                    $this->SendStatus(404);
                    return;
                }
                $this->SendHeader('Content-Type: ' . $type);
                echo $bytes;
                return;
            case 'save':
                $in = json_decode($this->Input(), true);
                $error = null;
                if (!is_array($in) || $this->Method() !== 'POST') {
                    $this->SendStatus(400);
                    return;
                }
                if (($in['op'] ?? '') === 'put') {
                    $note = NoteStore::Normalize($in['note'] ?? null);
                    $part = is_string($in['part'] ?? null) ? $in['part'] : '';
                    // Eigene Medien nur aus der eingerichteten Liste, eingebaute nur aus dem Katalog. Ein eingebauter
                    // Sticker bleibt gueltig, wenn der Schalter spaeter ausgeht: er liegt dann schon auf dem Brett.
                    if (!is_string($note)) {
                        $img = $note['img'];
                        if ((is_int($img) && $img > 0 && !$this->IsSticker($img)) || (is_string($img) && !$this->IsBuiltin($img))) {
                            $note = 'Unknown sticker';
                        }
                    }
                    $error = is_string($note) ? $note : $this->Mutate(static function (array $list) use ($note, $part): array {
                        [$next, $failed] = NoteStore::Put($list, $note, time(), $part);
                        return $failed === null ? [$next, null] : [$list, $failed];
                    });
                } elseif (($in['op'] ?? '') === 'del' && NoteStore::IsId($in['id'] ?? null)) {
                    $id = $in['id'];
                    $this->Mutate(static fn (array $list): array => [NoteStore::Remove($list, $id), null]);
                } else {
                    $this->SendStatus(400);
                    return;
                }
                $this->SendHeader('Content-Type: application/json; charset=utf-8');
                echo json_encode($this->State(-1) + ($error === null ? [] : ['error' => $this->Translate($error)]), self::JSON);
                return;
            default:
                $this->SendStatus(404);
        }
    }

    // Für die Tests ersetzbar
    protected function SendHeader(string $header): void
    {
        header($header);
    }

    protected function SendStatus(int $code): void
    {
        http_response_code($code);
    }

    protected function Input(): string
    {
        return (string) file_get_contents('php://input');
    }

    protected function Method(): string
    {
        return (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    // ── Intern ──

    /** Die eingerichteten Sticker: nur vorhandene Bild-Medien in einem auslieferbaren Format, ohne Dubletten. */
    private function Stickers(): array
    {
        $rows = json_decode($this->ReadPropertyString('Stickers'), true);
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $id = is_array($row) ? (int) ($row['Media'] ?? 0) : 0;
            if ($id <= 0 || isset($out[$id]) || $this->StickerProblem($id) !== '') {
                continue;
            }
            $name = is_array($row) && is_string($row['Name'] ?? null) && trim($row['Name']) !== '' ? trim($row['Name']) : IPS_GetName($id);
            $out[$id] = ['m' => $id, 'n' => mb_substr($name, 0, 60)];
        }
        $out = array_values($out);
        if ($this->ReadPropertyBoolean('BuiltinStickers')) {
            foreach (self::FLUENT as $key => $label) {
                if ($this->IsBuiltin($key)) {
                    $out[] = ['e' => $key, 'n' => $this->Translate($label)];
                }
            }
        }
        return $out;
    }

    /** Eingebauter Sticker: Name aus dem Katalog und die Datei liegt im Modul. */
    private function IsBuiltin(string $key): bool
    {
        return isset(self::FLUENT[$key]) && is_file(self::FLUENT_DIR . $key . '.png');
    }

    private function IsSticker(int $id): bool
    {
        foreach ($this->Stickers() as $sticker) {
            if (($sticker['m'] ?? 0) === $id) {
                return true;
            }
        }
        return false;
    }

    /** Warum sich ein Medienobjekt nicht als Sticker eignet, als englischer Schluessel; '' heisst: es geht. */
    private function StickerProblem(int $id): string
    {
        if ($id <= 0 || !IPS_MediaExists($id)) {
            return 'This media object does not exist.';
        }
        $media = IPS_GetMedia($id);
        if ((int) $media['MediaType'] !== MEDIATYPE_IMAGE) {
            return 'Only images can be used as stickers.';
        }
        if ($this->StickerType($id) === '') {
            return 'This image format cannot be delivered: PNG, JPEG, GIF, WebP or BMP.';
        }
        // MediaSize ist die rohe Groesse; der Hook liefert hoechstens 1 MiB aus
        if ((int) ($media['MediaSize'] ?? 0) > self::MAX_STICKER_BYTES) {
            return 'This image is too large (900 kB at most).';
        }
        return '';
    }

    /** Inhaltstyp eines Bild-Medienobjekts, oder '' wenn das Format nicht ausgeliefert wird. */
    private function StickerType(int $id): string
    {
        return self::IMAGE_TYPES[strtolower(pathinfo((string) IPS_GetMedia($id)['MediaFile'], PATHINFO_EXTENSION))] ?? '';
    }

    private function Load(): array
    {
        $list = json_decode($this->ReadAttributeString('Notes'), true);
        return is_array($list) ? $list : [];
    }

    /**
     * Liste unter Semaphor ändern (Visu, Kachel und Skripte schreiben gleichzeitig). `$change` liefert [Liste, Ergebnis].
     * Nur bei echter Änderung steigt die Folgenummer und die Kachel bekommt den Stand.
     */
    private function Mutate(callable $change): mixed
    {
        $sem = 'STICKY_' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($sem, 5000)) {
            throw new Exception($this->Translate('The board is busy'));
        }
        try {
            $before = $this->ReadAttributeString('Notes');
            [$list, $result] = $change($this->Load());
            $after = (string) json_encode(array_values($list), self::JSON);
            $changed = $after !== $before;
            if ($changed) {
                $this->WriteAttributeString('Notes', $after);
                $this->WriteAttributeInteger('Seq', $this->ReadAttributeInteger('Seq') + 1);
            }
        } finally {
            IPS_SemaphoreLeave($sem);
        }
        if ($changed) {
            $this->SetValue('Count', count($list));
            $this->UpdateVisualizationValue((string) json_encode(['type' => 'notes'] + $this->State(-1), self::JSON));
            $this->Nudge();
        }
        return $result;
    }

    /** Stand für Visu und Kachel; die Zettel fehlen, wenn der Empfänger die Folgenummer schon kennt. */
    private function State(int $since): array
    {
        $seq = $this->ReadAttributeInteger('Seq');
        return $since === $seq ? ['seq' => $seq] : ['seq' => $seq, 'notes' => $this->Load()];
    }

    /** Das Zettel-Skript mit eingesetzter Konfiguration: `overlay` fürs Fenster der Visu, `tile` für die Kachel. */
    private function Script(string $mode): string
    {
        $labels = [];
        foreach (self::LABELS as $text) {
            $labels[$text] = $this->Translate($text);
        }
        $config = [
            'owner'  => 'sticky-' . $this->InstanceID,
            'instance' => $this->InstanceID,
            'mode'   => $mode,
            'hook'   => $this->HookBase(),
            // Für die Kachel: Zettel über der Visu gewünscht, sie lädt k=agent.js notfalls selbst ins Fenster der Visu
            'float'  => $this->ReadPropertyBoolean('Overlay'),
            'pollMs' => min(60, max(1, $this->ReadPropertyInteger('PollSeconds'))) * 1000,
            'corner' => min(3, max(0, $this->ReadPropertyInteger('Corner'))),
            'max'    => NoteStore::MAX_NOTES,
            // Auswahl am Plus-Knopf; das Bild holt der Browser ueber k=img
            'stickers' => $this->Stickers(),
            'labels' => $labels,
            'state'  => $this->State(-1),
        ];
        // JSON_HEX_TAG: die Kachel bettet das Skript in einen <script>-Block ein
        return str_replace('/*STICKY_CONFIG*/null', (string) json_encode($config, self::JSON | JSON_HEX_TAG | JSON_HEX_AMP),
            (string) file_get_contents(__DIR__ . '/../libs/notes.js'));
    }

    private function HookBase(): string
    {
        $token = $this->ReadAttributeString('HookToken');
        return $this->GetBuffer('Hook') === '' || $token === '' ? '' : '/hook/' . self::HOOK . '/' . $this->InstanceID . '?t=' . $token;
    }
}
