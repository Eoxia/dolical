<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    class/dolicalvoicereport.class.php
 * \ingroup dolical
 * \brief   Writes the report of a meeting from a voice note, the way the quick creation of ReedCRM writes the
 *          description of an opportunity.
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/geturl.lib.php';

/**
 * Class DoliCalVoiceReport.
 *
 * Everything goes through OpenAI, with the ChatGPT setup of the AI module : the recording is transcribed, then
 * a chat model writes the report from the transcript and from what the event already tells (title, date,
 * people, third party).
 *
 * The models are the ones of the AI module, AI_API_CHATGPT_MODEL_TRANSCRIPT and AI_API_CHATGPT_MODEL_TEXT,
 * with the defaults of ReedCRM when it has none.
 */
class DoliCalVoiceReport
{
    /**
     * @var string OpenAI model transcribing the recording.
     */
    public const TRANSCRIPTION_MODEL = 'gpt-4o-transcribe';

    /**
     * @var string OpenAI model writing the report.
     */
    public const TEXT_MODEL = 'gpt-5';

    /**
     * @var string Endpoint of the OpenAI API when the AI module has none.
     */
    public const OPENAI_URL = 'https://api.openai.com/v1/';

    /**
     * @var int Largest recording the OpenAI transcription accepts, in bytes.
     */
    public const MAX_AUDIO_SIZE = 25 * 1024 * 1024;

    /**
     * @var int Time given to each API call, in seconds.
     */
    public const TIMEOUT = 90;

    /**
     * @var DoliDB Database handler.
     */
    public $db;

    /**
     * @var string Last error message.
     */
    public $error = '';

    /**
     * @var string Transcript of the last recording.
     */
    public $transcript = '';

    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler.
     */
    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     * Tell whether the AI module can write reports, on the same terms as the photo analysis of ReedCRM.
     *
     * @return bool True when the AI module is enabled on ChatGPT, with its key.
     */
    public static function isAvailable(): bool
    {
        return isModEnabled('ai') && getDolGlobalString('AI_API_SERVICE') == 'chatgpt' && getDolGlobalString('AI_API_CHATGPT_KEY') !== '' && getDolGlobalInt('DOLICAL_REPORT_FROM_VOICE', 1) > 0;
    }

    /**
     * Write the report of a meeting from a voice note.
     *
     * @param  string      $filePath Full path of the recording.
     * @param  array       $context  What the event tells : ['label', 'date', 'people', 'thirdparty', 'project', 'notes'].
     * @return string|null           Report, empty when the note holds nothing worth keeping; null on error, $this->error is then set.
     */
    public function write(string $filePath, array $context): ?string
    {
        $transcript = $this->transcribe($filePath);
        if ($transcript === null) {
            return null;
        }

        // Silence or noise : nothing to write up
        if (trim($transcript) === '') {
            return '';
        }

        return $this->writeReport($transcript, $context);
    }

    /**
     * Transcribe a recording with OpenAI.
     *
     * @param  string      $filePath Full path of the recording.
     * @return string|null           Transcript, null on error.
     */
    public function transcribe(string $filePath): ?string
    {
        global $langs;

        $this->transcript = '';

        $size = dol_is_file($filePath) ? dol_filesize($filePath) : 0;
        if ($size <= 0) {
            $this->error = $langs->transnoentities('DoliCalVoiceNoRecording');
            return null;
        }
        if ($size > self::MAX_AUDIO_SIZE) {
            $this->error = $langs->transnoentities('DoliCalVoiceTooLong');
            return null;
        }

        // Browsers record in webm (Chrome) or mp4 (Safari) whatever the name of the file, and OpenAI tells the
        // format by the extension of the name it is sent under
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $finfo->file($filePath);

        $body = [
            'file'            => new CURLFile($filePath, $mimeType, 'recording.' . self::audioExtension($mimeType, $filePath)),
            'model'           => getDolGlobalString('AI_API_CHATGPT_MODEL_TRANSCRIPT', self::TRANSCRIPTION_MODEL),
            'response_format' => 'json'
        ];
        // The language of the user, so a short note is not taken for another language
        if (preg_match('/^([a-z]{2})_/', (string) $langs->defaultlang, $matches)) {
            $body['language'] = $matches[1];
        }

        $url      = rtrim(getDolGlobalString('AI_API_CHATGPT_URL', self::OPENAI_URL), '/') . '/audio/transcriptions';
        $response = $this->post($url, $body, ['Authorization: Bearer ' . getDolGlobalString('AI_API_CHATGPT_KEY')]);
        if ($response === null) {
            return null;
        }

        if (!isset($response['text'])) {
            $this->error = $langs->transnoentities('DoliCalVoiceInvalidAnswer');
            return null;
        }

        $this->transcript = (string) $response['text'];

        return $this->transcript;
    }

    /**
     * Have the chat model write the report from the transcript of a voice note.
     *
     * @param  string      $transcript Transcript of the voice note.
     * @param  array       $context    What the event tells.
     * @return string|null             Report, empty when the note holds nothing worth keeping; null on error.
     */
    public function writeReport(string $transcript, array $context): ?string
    {
        global $langs;

        $about = [];
        foreach (['label' => 'Rendez-vous', 'date' => 'Date', 'people' => 'Participants', 'thirdparty' => 'Tiers', 'project' => 'Projet', 'notes' => 'Ordre du jour'] as $key => $name) {
            if (!empty($context[$key])) {
                $about[] = $name . ' : ' . $context[$key];
            }
        }

        $body = [
            'model'           => getDolGlobalString('AI_API_CHATGPT_MODEL_TEXT', self::TEXT_MODEL),
            'messages'        => [
                ['role' => 'system', 'content' => $this->getSystemPrompt()],
                ['role' => 'user', 'content' => "<rendez-vous>\n" . implode("\n", $about) . "\n</rendez-vous>\n<transcription>\n" . $transcript . "\n</transcription>"]
            ],
            // The answer is checked against the schema by OpenAI : no text around the report
            'response_format' => [
                'type'        => 'json_schema',
                'json_schema' => [
                    'name'   => 'meeting_report',
                    'strict' => true,
                    'schema' => [
                        'type'                 => 'object',
                        'properties'           => ['report' => ['type' => 'string']],
                        'required'             => ['report'],
                        'additionalProperties' => false
                    ]
                ]
            ]
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . getDolGlobalString('AI_API_CHATGPT_KEY')
        ];

        $url      = rtrim(getDolGlobalString('AI_API_CHATGPT_URL', self::OPENAI_URL), '/') . '/chat/completions';
        $response = $this->post($url, (string) json_encode($body), $headers);
        if ($response === null) {
            return null;
        }

        $choice = $response['choices'][0] ?? [];

        // A refusal or a cut answer carries no usable JSON
        $finishReason = (string) ($choice['finish_reason'] ?? '');
        if (!empty($choice['message']['refusal']) || $finishReason === 'length' || $finishReason === 'content_filter') {
            $this->error = $langs->transnoentities('DoliCalVoiceNotWritten', !empty($choice['message']['refusal']) ? (string) $choice['message']['refusal'] : $finishReason);
            return null;
        }

        $answer = json_decode((string) ($choice['message']['content'] ?? ''), true);
        if (!is_array($answer) || !isset($answer['report'])) {
            $this->error = $langs->transnoentities('DoliCalVoiceInvalidAnswer');
            return null;
        }

        return trim((string) $answer['report']);
    }

    /**
     * Instructions given to the chat model.
     *
     * @return string System prompt.
     */
    private function getSystemPrompt(): string
    {
        return "Tu rédiges le compte-rendu d'un rendez-vous dans DoliCal, l'agenda d'un CRM utilisé par des commerciaux et des équipes de terrain. "
            . "Tu reçois ce que l'agenda sait du rendez-vous et la transcription automatique d'un mémo vocal enregistré juste après par un participant.\n\n"
            . "Écris un compte-rendu qui permettra à un collègue absent de savoir ce qui s'est passé et ce qui reste à faire :\n"
            . "- l'objet de l'échange et les points abordés ;\n"
            . "- les décisions prises et ce qui a été promis ;\n"
            . "- les prochaines étapes, avec qui s'en charge et pour quand ;\n"
            . "- les informations concrètes citées : personnes, montants, quantités, dates, délais, concurrents.\n\n"
            . "Reste fidèle au mémo : n'ajoute aucune information qui n'y figure pas, ne déduis rien de ce que l'agenda dit du rendez-vous "
            . "(il ne sert qu'à comprendre le contexte et à orthographier les noms) et ne devine pas ce qui manque. "
            . "La transcription peut contenir des erreurs de reconnaissance : corrige celles qui sont évidentes d'après le contexte, "
            . "mais recopie tels quels les noms propres, chiffres et coordonnées dont tu n'es pas sûr. "
            . "Laisse de côté les hésitations, les répétitions et les apartés sans rapport.\n\n"
            . "Écris en texte brut, dans la langue du mémo : des phrases courtes, et une liste à tirets pour les prochaines étapes s'il y en a. "
            . "Pas de titre, pas de phrase d'introduction, pas de mise en forme Markdown. "
            . "Si le mémo ne contient rien d'utile (silence, bruit, essai du micro), renvoie un compte-rendu vide.";
    }

    /**
     * Extension under which the recording is sent, from its actual format.
     *
     * @param  string $mimeType MIME type read in the file.
     * @param  string $filePath Full path of the recording, its own extension being the fallback.
     * @return string           Extension known by the OpenAI transcription.
     */
    public static function audioExtension(string $mimeType, string $filePath): string
    {
        $extensions = [
            'audio/webm'     => 'webm',
            'video/webm'     => 'webm',
            'audio/mp4'      => 'mp4',
            'video/mp4'      => 'mp4',
            'audio/x-m4a'    => 'm4a',
            'audio/ogg'      => 'ogg',
            'audio/mpeg'     => 'mp3',
            'audio/flac'     => 'flac',
            'audio/wav'      => 'wav',
            'audio/x-wav'    => 'wav',
            'audio/vnd.wave' => 'wav'
        ];

        return $extensions[$mimeType] ?? strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    }

    /**
     * Post a request and decode the JSON answer.
     *
     * @param  string                     $url     Endpoint.
     * @param  array<string,mixed>|string $body    Fields sent as multipart, or JSON already encoded.
     * @param  string[]                   $headers Extra headers, the authentication included.
     * @return array<string,mixed>|null            Decoded answer, null on error, $this->error is then set.
     */
    protected function post(string $url, $body, array $headers): ?array
    {
        global $langs;

        $this->error = '';

        $result   = getURLContent($url, is_array($body) ? 'POST' : 'POSTALREADYFORMATED', $body, 1, $headers, ['http', 'https'], 0, -1, 0, self::TIMEOUT);
        $httpCode = (int) ($result['http_code'] ?? 0);

        if (!empty($result['curl_error_msg'])) {
            $this->error = $result['curl_error_msg'];
            dol_syslog(__METHOD__ . ' ' . $url . ' curl error ' . $this->error, LOG_ERR);
            return null;
        }

        $decoded = json_decode((string) ($result['content'] ?? ''), true);

        if ($httpCode < 200 || $httpCode >= 300) {
            // OpenAI answers a JSON envelope on error, its message says more than the HTTP code
            $this->error = (string) ($decoded['error']['message'] ?? $langs->transnoentities('DoliCalVoiceHttpError', (string) $httpCode));
            dol_syslog(__METHOD__ . ' ' . $url . ' HTTP ' . $httpCode . ' ' . $this->error, LOG_ERR);
            return null;
        }

        if (!is_array($decoded)) {
            $this->error = $langs->transnoentities('DoliCalVoiceInvalidAnswer');
            return null;
        }

        return $decoded;
    }
}
