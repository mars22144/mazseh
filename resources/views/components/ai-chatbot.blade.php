@php
    $waNumber = preg_replace('/[^0-9]/', '', (string) config('services.whatsapp.admin_number', '6285785180423'));
    $quickPrompts = [
        'Rekomendasi mobil untuk perjalanan keluarga' => 'Mobil untuk keluarga',
        'Apa saja syarat sewa lepas kunci?' => 'Syarat lepas kunci',
        'Berapa tarif sewa Toyota Fortuner dan Innova Zenix?' => 'Tarif Fortuner & Zenix',
        'Hubungkan saya dengan admin via WhatsApp' => 'Bicara dengan staf',
    ];
@endphp

<button type="button" class="ai-fab" id="ai-chat-toggle" aria-label="Buka Asisten AI MAZSEH" aria-controls="ai-chat-window" aria-expanded="false">
    <span class="ai-orb" aria-hidden="true">
        <x-icon name="spark" />
    </span>
    <span class="ai-fab-label">Tanya AI MAZSEH</span>
    <span class="ai-status-dot" aria-hidden="true"></span>
</button>

<section class="ai-window" id="ai-chat-window" data-state="closed" role="dialog" aria-modal="false" aria-labelledby="ai-chat-title" inert>
    <header class="ai-header">
        <span class="ai-orb ai-orb-lg" aria-hidden="true">
            <x-icon name="spark" />
        </span>
        <div class="ai-header-text">
            <strong id="ai-chat-title">Asisten MAZSEH</strong>
            <span><span class="ai-status-dot" aria-hidden="true"></span> Online &middot; siap 24 jam</span>
        </div>
        <div class="ai-header-actions">
            <button type="button" class="ai-icon-btn" id="ai-chat-clear" aria-label="Mulai percakapan baru" title="Mulai percakapan baru">
                <x-icon name="edit" />
            </button>
            <button type="button" class="ai-icon-btn" id="ai-chat-close" aria-label="Tutup jendela chat" title="Tutup">
                <x-icon name="down" />
            </button>
        </div>
    </header>

    <div class="ai-body" id="ai-chat-body" aria-live="polite">
        <div class="ai-welcome">
            <span class="ai-orb ai-orb-xl" aria-hidden="true">
                <x-icon name="spark" />
            </span>
            <h2>Halo! Mau ke mana hari ini?</h2>
            <p>Tanyakan rekomendasi mobil, ketersediaan unit, tarif, atau syarat sewa. Saya jawab dalam hitungan detik.</p>
        </div>

        <div class="ai-quick-prompts" id="ai-quick-prompts">
            @foreach($quickPrompts as $prompt => $label)
                <button type="button" class="ai-prompt-btn" data-prompt="{{ $prompt }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="ai-footer">
        <a class="ai-handoff" href="https://wa.me/{{ $waNumber }}?text=Halo%20Admin%20MAZSEH,%20saya%20ingin%20konsultasi%20sewa%20mobil." target="_blank" rel="noopener">
            <x-icon name="phone" />
            <span>Butuh staf? <strong>Chat WhatsApp</strong></span>
        </a>
        <form class="ai-composer" id="ai-chat-form">
            @csrf
            <label for="ai-chat-input" class="sr-only">Tulis pertanyaan</label>
            <input type="text" class="ai-input" id="ai-chat-input" placeholder="Tulis pertanyaan…" autocomplete="off" maxlength="1000" required>
            <button type="submit" class="ai-send-btn" id="ai-chat-send" aria-label="Kirim pesan" disabled>
                <x-icon name="arrow" />
            </button>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const STORAGE_HISTORY_KEY = 'mazseh_ai_chat_history_v1';
    const STORAGE_OPEN_KEY = 'mazseh_ai_chat_open_v1';
    const CHEVRON_PATH = 'm9 5 7 7-7 7';
    const PHONE_PATH = 'm5 3 4 5-2 3a15 15 0 0 0 6 6l3-2 5 4c-5 7-23-11-16-16Z';

    const toggle = document.getElementById('ai-chat-toggle');
    const closeBtn = document.getElementById('ai-chat-close');
    const clearBtn = document.getElementById('ai-chat-clear');
    const windowEl = document.getElementById('ai-chat-window');
    const form = document.getElementById('ai-chat-form');
    const input = document.getElementById('ai-chat-input');
    const sendBtn = document.getElementById('ai-chat-send');
    const body = document.getElementById('ai-chat-body');

    if (!toggle || !windowEl || !form || !input || !body) return;

    const initialMarkup = body.innerHTML;
    let chatMessages = loadHistory();
    let isSending = false;

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

    function rememberOpenState(isOpen) {
        try { sessionStorage.setItem(STORAGE_OPEN_KEY, isOpen ? '1' : '0'); } catch (e) {}
    }

    function scrollToLatest(smooth = true) {
        body.scrollTo({ top: body.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
    }

    function setOpen(isOpen, { animate = true, restoreFocus = true } = {}) {
        if (!animate) {
            windowEl.classList.add('is-instant');
            toggle.classList.add('is-instant');
        }

        windowEl.dataset.state = isOpen ? 'open' : 'closed';
        windowEl.toggleAttribute('inert', !isOpen);
        toggle.classList.toggle('is-hidden', isOpen);
        toggle.setAttribute('aria-expanded', String(isOpen));
        rememberOpenState(isOpen);

        if (isOpen) {
            scrollToLatest(false);
            input.focus({ preventScroll: true });
        } else if (restoreFocus) {
            toggle.focus({ preventScroll: true });
        }

        if (!animate) {
            requestAnimationFrame(() => requestAnimationFrame(() => {
                windowEl.classList.remove('is-instant');
                toggle.classList.remove('is-instant');
            }));
        }
    }

    function bindQuickPrompts() {
        body.querySelectorAll('.ai-prompt-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const promptText = btn.getAttribute('data-prompt');
                if (promptText && !isSending) {
                    input.value = promptText;
                    syncSendState();
                    form.requestSubmit();
                }
            });
        });
    }

    function removeIntro() {
        body.querySelectorAll('#ai-quick-prompts, .ai-welcome').forEach(el => el.remove());
    }

    function renderStoredHistory() {
        if (chatMessages.length === 0) return;
        removeIntro();

        chatMessages.forEach(item => {
            const domRole = item.role === 'model' ? 'bot' : 'user';
            appendBubble(item.text, domRole, item.time, false);
            if (domRole === 'bot' && Array.isArray(item.recommended_cars) && item.recommended_cars.length > 0) {
                appendCarActions(item.recommended_cars, false);
            }
            if (domRole === 'bot' && item.whatsapp_url) {
                appendWhatsappAction(item.whatsapp_url, false);
            }
        });
    }

    function syncSendState() {
        sendBtn.disabled = isSending || input.value.trim() === '';
    }

    bindQuickPrompts();
    renderStoredHistory();

    // Pulihkan status buka/tutup jendela saat berpindah halaman tanpa animasi masuk
    try {
        if (sessionStorage.getItem(STORAGE_OPEN_KEY) === '1') {
            setOpen(true, { animate: false });
        }
    } catch (e) {}

    toggle.addEventListener('click', () => setOpen(windowEl.dataset.state !== 'open'));
    closeBtn.addEventListener('click', () => setOpen(false));
    windowEl.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setOpen(false);
    });
    input.addEventListener('input', syncSendState);

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
        if (!text || isSending) return;

        removeIntro();

        const historyPayload = chatMessages.slice(-8).map(item => ({
            role: item.role,
            text: item.text
        }));

        const userTime = formatTime();
        appendBubble(text, 'user', userTime);
        chatMessages.push({ role: 'user', text: text, time: userTime });
        saveHistory();

        input.value = '';
        isSending = true;
        syncSendState();

        const typingEl = appendTypingIndicator();

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

            typingEl.remove();

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
            typingEl.remove();
            appendBubble('Koneksi terputus. Silakan coba kembali atau hubungi WhatsApp kami.', 'bot');
        } finally {
            isSending = false;
            syncSendState();
            input.focus();
        }
    });

    function createIcon(path) {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'od-icon');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('aria-hidden', 'true');
        const pathEl = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        pathEl.setAttribute('d', path);
        svg.appendChild(pathEl);
        return svg;
    }

    function appendRow(role, node, animate = true) {
        const row = document.createElement('div');
        row.className = 'ai-row ' + role + (animate ? ' is-new' : '');
        row.appendChild(node);
        body.appendChild(row);
        scrollToLatest(animate);
        return row;
    }

    function appendBubble(text, role, timeStr = formatTime(), animate = true) {
        const bubble = document.createElement('div');
        bubble.className = 'ai-bubble ' + role;
        bubble.innerHTML = escapeHtml(text).replace(/\n/g, '<br>');

        const time = document.createElement('span');
        time.className = 'ai-bubble-time';
        time.textContent = timeStr;
        bubble.appendChild(time);

        return appendRow(role, bubble, animate);
    }

    function appendTypingIndicator() {
        const bubble = document.createElement('div');
        bubble.className = 'ai-bubble bot ai-typing';
        bubble.setAttribute('aria-label', 'Asisten sedang mengetik');
        bubble.innerHTML = '<span></span><span></span><span></span>';
        return appendRow('bot', bubble);
    }

    function appendCarActions(cars, animate = true) {
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
            const isAvailable = car.status === 'tersedia';
            const statusEl = document.createElement('span');
            statusEl.className = 'ai-car-status ' + (isAvailable ? 'is-available' : 'is-maintenance');
            statusEl.textContent = isAvailable ? 'Tersedia' : 'Perawatan';
            metaEl.append(`${String(car.price || '')}/hari · `, statusEl);

            infoDiv.append(titleEl, metaEl);
            link.append(infoDiv, createIcon(CHEVRON_PATH));
            wrapper.appendChild(link);
        });

        if (wrapper.children.length > 0) {
            appendRow('bot', wrapper, animate);
        }
    }

    function appendWhatsappAction(url, animate = true) {
        if (typeof url !== 'string' || !url.startsWith('https://wa.me/')) {
            return;
        }

        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.className = 'ai-wa-btn';
        link.append(createIcon(PHONE_PATH), 'Lanjutkan di WhatsApp');

        appendRow('bot', link, animate);
    }

    function escapeHtml(str) {
        const p = document.createElement('p');
        p.textContent = str;
        return p.innerHTML;
    }
});
</script>
