<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/NoteStore.php';

/**
 * Klebezettel, die frei über der Visualisierung liegen (über die Browser-Erweiterung
 * „Visu-Erweiterung für Symcon“) und als Brett in einer Kachel. Die Zettel liegen im Attribut `Notes`;
 * Visu und Kachel lesen und schreiben über den Hook /hook/sticky/<ID>?t=<Token>.
 */
class StickyNotes extends IPSModuleStrict
{
    private const HOOK = 'sticky';
    private const COLOR_NAMES = ['gelb' => 'yellow', 'rosa' => 'pink', 'grün' => 'green', 'gruen' => 'green', 'blau' => 'blue'];
    private const LABELS = ['New note', 'Show or hide notes', 'Type', 'Write by hand', 'Undo last stroke', 'Pen color', 'Note color',
        'Tear off', 'Tear off?', 'Done', 'The board is full', 'No notes'];
    private const JSON = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyBoolean('Overlay', true);
        $this->RegisterPropertyInteger('Corner', 3);
        $this->RegisterPropertyInteger('PollSeconds', 3);
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
        $base = $this->HookBase();
        // Verbindungsadresse für die Browser-Erweiterung „Visu-Erweiterung für Symcon“, ohne Host
        if ($base !== '') {
            $form['actions'][] = ['type' => 'ValidationTextBox', 'name' => 'ExtensionAddress', 'caption' => 'Address for the browser extension',
                'width' => '700px', 'value' => $base . '&k=ext'];
        }
        return (string) json_encode($form, self::JSON);
    }

    /**
     * /hook/sticky/<ID>?t=<Token>&k=…
     *   k=ext                 Beschreibung für die Browser-Erweiterung (nennt k=agent.js)
     *   k=agent.js            Zettel-Skript für das Fenster der Visu (nur mit „Zettel über der Visu“); lädt die Erweiterung oder die Kachel
     *   k=poll&since=<seq>    Stand; die Zettel nur, wenn sich seit <seq> etwas geändert hat
     *   k=save  (POST, JSON)  {op: put, note, part?: place|content} | {op: del, id}; antwortet mit dem Stand
     */
    protected function ProcessHookData(): void
    {
        $token = $this->ReadAttributeString('HookToken');
        $given = isset($_GET['t']) && is_string($_GET['t']) ? $_GET['t'] : '';
        if ($token === '' || !hash_equals($token, $given)) {
            $this->SendStatus(403);
            return;
        }
        $this->SendHeader('X-Content-Type-Options: nosniff');
        $this->SendHeader('Cache-Control: no-store');
        $overlay = $this->ReadPropertyBoolean('Overlay');
        switch (isset($_GET['k']) && is_string($_GET['k']) ? $_GET['k'] : '') {
            case 'ext':
                $this->SendHeader('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'visuExtension' => 1,
                    'name'          => IPS_GetName($this->InstanceID),
                    'scripts'       => $overlay ? [$this->HookBase() . '&k=agent.js'] : [],
                    'info'          => $overlay ? '' : $this->Translate('"Show notes on top of the visualization" is switched off.'),
                ], self::JSON);
                return;
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
            'mode'   => $mode,
            'hook'   => $this->HookBase(),
            // Für die Kachel: Zettel über der Visu gewünscht, sie lädt k=agent.js notfalls selbst ins Fenster der Visu
            'float'  => $this->ReadPropertyBoolean('Overlay'),
            'pollMs' => min(60, max(1, $this->ReadPropertyInteger('PollSeconds'))) * 1000,
            'corner' => min(3, max(0, $this->ReadPropertyInteger('Corner'))),
            'max'    => NoteStore::MAX_NOTES,
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
