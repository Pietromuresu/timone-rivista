<?php

namespace App\Http\Controllers;

use App\Support\Manual\ManualAssistantDesk;
use App\Support\Manual\ManualContexts;
use App\Support\Manual\ManualFeedbackRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * «Chiedi al manuale» (2026-10-29): due chiamate JSON dal pannello
 * dell'assistente. Solo orchestrazione: la scelta della pagina è in
 * App\Support\Manual\ManualAssistantDesk, i voti in ManualFeedbackRecorder.
 * Visibile a ogni utente loggato, come il manuale.
 */
class ManualAssistantController extends Controller
{
    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domanda' => ['required', 'string', 'max:300'],
            'contesto' => ['nullable', 'string', Rule::in(array_keys(ManualContexts::PAGES))],
        ]);

        return response()->json(ManualAssistantDesk::fromConfig()->ask($data['domanda'], $data['contesto'] ?? null));
    }

    public function vote(Request $request, ManualFeedbackRecorder $recorder): JsonResponse
    {
        $data = $request->validate([
            'ricevuta' => ['required', 'string', 'max:4000'],
            'voto' => ['nullable', 'required_without:scelta', Rule::in(['su', 'giu'])],
            'scelta' => ['nullable', 'required_without:voto', 'string', 'max:100'],
        ]);

        $result = $recorder->record($data['ricevuta'], $data['voto'] ?? null, $data['scelta'] ?? null, $request->user()->getKey());

        return response()->json(['esito' => $result], match ($result) {
            ManualFeedbackRecorder::OK => 200,
            ManualFeedbackRecorder::ALREADY_VOTED => 409,
            default => 422,
        });
    }
}
