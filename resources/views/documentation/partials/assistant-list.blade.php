{{-- Pagine alternative dell'assistente (dopo «Non è questo» o in «Forse
     cercavi»): titolo, «In breve» apribile, e «👍 È questa» una volta sola. --}}
<ol class="manual-assistant-list">
    <template x-for="(page, i) in result.alternative" x-bind:key="page.slug">
        <li class="manual-assistant-item" x-bind:class="chosen === page.slug && 'is-chosen'">
            <details x-bind:open="chosen === page.slug">
                <summary x-text="page.titolo"></summary>
                <div class="manuale-content manual-assistant-inbreve" x-html="page.in_breve"></div>
                <a x-bind:href="page.url" class="manual-assistant-more">Leggi tutto →</a>
            </details>
            <button type="button" class="manual-assistant-vote" x-show="!chosen" x-on:click="choose(page.slug)" data-testid="manual-assistant-choose">👍 È questa</button>
            <span x-show="chosen === page.slug" class="manual-assistant-note">Grazie!</span>
        </li>
    </template>
</ol>
