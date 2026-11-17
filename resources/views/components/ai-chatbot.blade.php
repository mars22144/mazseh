<button type="button" class="ai-fab" id="ai-chat-toggle" aria-label="Buka Asisten AI MAZSEH">
    <div class="ai-fab-icon">
        <x-icon name="spark" style="--od-icon-size: 16px;" />
    </div>
    <span>Tanya AI MAZSEH</span>
    <span class="ai-fab-dot"></span>
</button>

@php
    $waNumber = preg_replace('/[^0-9]/', '', (string) config('services.whatsapp.admin_number', '6285785180423'));
@endphp

<div class="ai-window" id="ai-chat-window" hidden>
    <div class="ai-header">
        <div class="ai-header-title">
            <div class="ai-fab-icon" style="width:28px;height:28px;">
                <x-icon name="spark" style="--od-icon-size: 16px;" />
            </div>
            <div>
                <strong>Asisten AI MAZSEH</strong>
                <span><span class="ai-fab-dot"></span> Siap melayani 24/7</span>
            </div>
        </div>
        <div class="ai-header-actions">
            <button type="button" class="ai-close-btn" id="ai-chat-clear" aria-label="Bersihkan riwayat chat" title="Bersihkan chat">
                <x-icon name="trash" />
            </button>
            <button type="button" class="ai-close-btn" id="ai-chat-close" aria-label="Tutup jendela chat" title="Tutup">
                <x-icon name="close" />
            </button>
        </div>
    </div>

    <div class="ai-whatsapp-bar">
        <span>Butuh bantuan staf langsung?</span>
        <a href="https://wa.me/{{ $waNumber }}?text=Halo%20Admin%20MAZSEH,%20saya%20ingin%20konsultasi%20sewa%20mobil." target="_blank" rel="noopener">
            <x-icon name="phone" style="--od-icon-size: 14px;" />
            <span>WhatsApp Admin</span>
        </a>
    </div>

    <div class="ai-body" id="ai-chat-body">
        <div class="ai-bubble bot">
            Halo! Saya Asisten Cerdas MAZSEH. Silakan tanyakan rekomendasi armada, cek ketersediaan unit, atau persyaratan sewa mobil Anda.
            <span class="ai-bubble-time">{{ date('H:i') }}</span>
        </div>

        <div class="ai-quick-prompts" id="ai-quick-prompts">
            <button type="button" class="ai-prompt-btn" data-prompt="Rekomendasi mobil untuk perjalanan keluarga">Rekomendasi mobil keluarga</button>
            <button type="button" class="ai-prompt-btn" data-prompt="Apa saja syarat sewa lepas kunci?">Syarat sewa lepas kunci</button>
            <button type="button" class="ai-prompt-btn" data-prompt="Berapa tarif sewa Toyota Fortuner dan Innova Zenix?">Tarif Fortuner & Zenix</button>
            <button type="button" class="ai-prompt-btn" data-prompt="Hubungkan saya dengan admin via WhatsApp">Chat Admin WhatsApp</button>
        </div>
    </div>

    <form class="ai-footer" id="ai-chat-form">
        @csrf
        <input type="text" class="ai-input" id="ai-chat-input" placeholder="Ketik pertanyaan Anda..." autocomplete="off" maxlength="1000" required>
        <button type="submit" class="ai-send-btn" aria-label="Kirim pesan">
            <x-icon name="arrow" style="--od-icon-size: 18px;" />
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const STORAGE_HISTORY_KEY = 'mazseh_ai_chat_history_v1';
    const STORAGE_OPEN_KEY = 'mazseh_ai_chat_open_v1';

    const toggle = document.getElementById('ai-chat-toggle');
    const closeBtn = document.getElementById('ai-chat-close');
    const clearBtn = document.getElementById('ai-chat-clear');
    const windowEl = document.getElementById('ai-chat-window');
    const form = document.getElementById('ai-chat-form');
    const input = document.getElementById('ai-chat-input');
    const body = document.getElementById('ai-chat-body');

    if (!toggle || !windowEl || !form || !input || !body) return;

    const initialMarkup = body.innerHTML;
    let chatMessages = loadHistory();

    function formatTime(date = new Date()) {
        return String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
    }

    function loadHistory() {
        try {
            const raw = sessionStorage.getItem(STORAGE_HISTORY_KEY);
            const parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function saveHistory() {
        try {
            sessionStorage.setItem(STORAGE_HISTORY_KEY, JSON.stringify(chatMessages.slice(-20)));
        } catch (e) {
            // Abaikan jika sessionStorage penuh atau diblokir
        }
    }

    function bindQuickPrompts() {
        body.querySelectorAll('.ai-prompt-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const promptText = btn.getAttribute('data-prompt');
                if (promptText) {
                    input.value = promptText;
                    form.dispatchEvent(new Event('submit'));
                }
            });
        });
    }

    function renderStoredHistory() {
        if (chatMessages.length === 0) return;

        const quickPromptsEl = document.getElementById('ai-quick-prompts');
        if (quickPromptsEl) quickPromptsEl.remove();

        chatMessages.forEach(item => {
            const domRole = item.role === 'model' ? 'bot' : 'user';
            appendBubble(item.text, domRole, item.time);
            if (domRole === 'bot' && Array.isArray(item.recommended_cars) && item.recommended_cars.length > 0) {
                appendCarActions(item.recommended_cars);
            }
            if (domRole === 'bot' && item.whatsapp_url) {
                appendWhatsappAction(item.whatsapp_url);
            }
        });
    }

    // Pulihkan status buka/tutup jendela saat berpindah halaman
    try {
        if (sessionStorage.getItem(STORAGE_OPEN_KEY) === '1') {
            windowEl.removeAttribute('hidden');
        }
    } catch (e) {}

    bindQuickPrompts();
    renderStoredHistory();

    toggle.addEventListener('click', () => {
        const isHidden = windowEl.hasAttribute('hidden');
        if (isHidden) {
            windowEl.removeAttribute('hidden');
            try { sessionStorage.setItem(STORAGE_OPEN_KEY, '1'); } catch (e) {}
            body.scrollTop = body.scrollHeight;
            input.focus();
        } else {
            windowEl.setAttribute('hidden', '');
            try { sessionStorage.setItem(STORAGE_OPEN_KEY, '0'); } catch (e) {}
        }
    });

    closeBtn.addEventListener('click', () => {
        windowEl.setAttribute('hidden', '');
        try { sessionStorage.setItem(STORAGE_OPEN_KEY, '0'); } catch (e) {}
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            chatMessages = [];
            try { sessionStorage.removeItem(STORAGE_HISTORY_KEY); } catch (e) {}
            body.innerHTML = initialMarkup;
            bindQuickPrompts();
            input.focus();
        });
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const text = input.value.trim();
        if (!text) return;

        const historyPayload = chatMessages.slice(-8).map(item => ({
            role: item.role,
            text: item.text
        }));

        const userTime = formatTime();
        appendBubble(text, 'user', userTime);
        chatMessages.push({ role: 'user', text: text, time: userTime });
        saveHistory();

        input.value = '';
        input.disabled = true;

        const loadingId = 'ai-loading-' + Date.now();
        const loadingEl = document.createElement('div');
        loadingEl.className = 'ai-bubble bot';
        loadingEl.id = loadingId;
        loadingEl.innerHTML = '<em>Sedang mengetik...</em>';
        body.appendChild(loadingEl);
        body.scrollTop = body.scrollHeight;

        try {
            const resp = await fetch('{{ route("ai-chat.send") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ message: text, history: historyPayload })
            });

            const loadingBubble = document.getElementById(loadingId);
            if (loadingBubble) loadingBubble.remove();

            if (resp.status === 429) {
                appendBubble('Anda mengirim pesan terlalu cepat. Mohon tunggu sebentar sebelum bertanya kembali.', 'bot');
                return;
            }

            const data = await resp.json();

            if (resp.ok && data && data.reply) {
                const botTime = formatTime();
                const recommendedCars = Array.isArray(data.recommended_cars) ? data.recommended_cars : [];
                const waUrl = (data.status === 'dialihkan_ke_wa' && data.whatsapp_url) ? data.whatsapp_url : null;

                appendBubble(data.reply, 'bot', botTime);
                if (recommendedCars.length > 0) {
                    appendCarActions(recommendedCars);
                }
                if (waUrl) {
                    appendWhatsappAction(waUrl);
                }

                chatMessages.push({
                    role: 'model',
                    text: data.reply,
                    time: botTime,
                    recommended_cars: recommendedCars,
                    whatsapp_url: waUrl
                });
                saveHistory();
            } else {
                appendBubble('Maaf, sistem sedang sibuk. Silakan hubungi tim kami via WhatsApp.', 'bot');
            }
        } catch (err) {
            const loadingBubble = document.getElementById(loadingId);
            if (loadingBubble) loadingBubble.remove();
            appendBubble('Koneksi terputus. Silakan coba kembali atau hubungi WhatsApp kami.', 'bot');
        } finally {
            input.disabled = false;
            input.focus();
        }
    });

    function appendBubble(text, role, timeStr = formatTime()) {
        const div = document.createElement('div');
        div.className = 'ai-bubble ' + role;
        div.innerHTML = escapeHtml(text).replace(/\n/g, '<br>') + `<span class="ai-bubble-time">${escapeHtml(timeStr)}</span>`;
        body.appendChild(div);
        body.scrollTop = body.scrollHeight;
    }

    function appendCarActions(cars) {
        const wrapper = document.createElement('div');
        wrapper.className = 'ai-car-actions';

        cars.forEach(car => {
            const url = String(car.url || '');
            if (!url.startsWith('/') && !url.startsWith(window.location.origin + '/')) {
                return;
            }

            const link = document.createElement('a');
            link.href = url;
            link.className = 'ai-car-card-link';

            const infoDiv = document.createElement('div');
            const titleEl = document.createElement('strong');
            titleEl.textContent = String(car.name || 'Unit Mobil');

            const metaEl = document.createElement('span');
            metaEl.className = 'ai-car-card-meta';
            const statusLabel = car.status === 'tersedia' ? 'Tersedia' : 'Maintenance';
            metaEl.textContent = `${String(car.price || '')}/hari • ${statusLabel}`;

            infoDiv.appendChild(titleEl);
            infoDiv.appendChild(metaEl);

            const ctaEl = document.createElement('span');
            ctaEl.className = 'ai-car-card-cta';
            ctaEl.textContent = 'Lihat Unit →';

            link.appendChild(infoDiv);
            link.appendChild(ctaEl);
            wrapper.appendChild(link);
        });

        if (wrapper.children.length > 0) {
            body.appendChild(wrapper);
            body.scrollTop = body.scrollHeight;
        }
    }

    function appendWhatsappAction(url) {
        if (typeof url !== 'string' || !url.startsWith('https://wa.me/')) {
            return;
        }

        const div = document.createElement('div');
        div.style.marginTop = '4px';

        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.className = 'btn btn-primary btn-small wfull';
        link.style.background = '#16a34a';
        link.style.color = 'white';
        link.style.display = 'inline-flex';
        link.style.gap = '6px';
        link.style.justifyContent = 'center';
        link.textContent = 'Hubungi via WhatsApp Langsung';

        div.appendChild(link);
        body.appendChild(div);
        body.scrollTop = body.scrollHeight;
    }

    function escapeHtml(str) {
        const p = document.createElement('p');
        p.textContent = str;
        return p.innerHTML;
    }
});
</script>
