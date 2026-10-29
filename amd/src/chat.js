// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Streaming chat client for styled FredKI chatbots.
 *
 * @module     local_fredkichat/chat
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const escapeHtml = (value) => String(value)
    .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;').replaceAll("'", '&#039;');

const safeUrl = (url) => {
    try {
        const parsed = new URL(url, window.location.origin);
        return ['http:', 'https:'].includes(parsed.protocol) ? parsed.href : '';
    } catch (e) {
        return '';
    }
};

const renderMarkdown = (text) => {
    const inline = (value) => {
        let out = escapeHtml(value);
        out = out.replace(/!\[([^\]]*)\]\((https?:\/\/[^\s)]+)\)/g, (match, alt, url) => {
            const clean = safeUrl(url.replaceAll('&amp;', '&'));
            return clean ? `<img src="${escapeHtml(clean)}" alt="${alt}" loading="lazy">` : match;
        });
        out = out.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, (match, label, url) => {
            const clean = safeUrl(url.replaceAll('&amp;', '&'));
            return clean ? `<a href="${escapeHtml(clean)}" target="_blank" rel="noopener noreferrer">${label}</a>` : match;
        });
        out = out.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        return out;
    };

    const splitTableRow = (line) => {
        let row = String(line).trim();
        if (row.startsWith('|')) {
            row = row.slice(1);
        }
        if (row.endsWith('|')) {
            row = row.slice(0, -1);
        }
        return row.split(/(?<!\\)\|/).map((cell) => cell.trim().replace(/\\\|/g, '|'));
    };

    const isTableSeparator = (line) => {
        const cells = splitTableRow(line);
        return cells.length > 1 && cells.every((cell) => /^:?-{3,}:?$/.test(cell.replace(/\s+/g, '')));
    };

    const renderTable = (headerLine, bodyLines) => {
        const headers = splitTableRow(headerLine);
        const rows = bodyLines.map(splitTableRow);
        const width = headers.length;
        const headerHtml = headers.map((cell) => `<th scope="col">${inline(cell)}</th>`).join('');
        const rowsHtml = rows.map((row) => {
            const cells = Array.from({length: width}, (_, index) => row[index] ?? '');
            return `<tr>${cells.map((cell) => `<td>${inline(cell)}</td>`).join('')}</tr>`;
        }).join('');
        return `<div class="fredkichat-table-wrap" role="region" aria-label="Tabelle" tabindex="0"><table><thead><tr>${headerHtml}</tr></thead><tbody>${rowsHtml}</tbody></table></div>`;
    };

    const lines = String(text).replace(/\r\n?/g, '\n').split('\n');
    const html = [];
    let paragraph = [];
    let listType = null;
    let listItems = [];
    let listStart = 1;
    let orderedCounter = 0;

    const flushParagraph = () => {
        if (!paragraph.length) {
            return;
        }
        html.push(`<p>${inline(paragraph.join(' '))}</p>`);
        paragraph = [];
    };
    const flushList = () => {
        if (!listType || !listItems.length) {
            listType = null;
            listItems = [];
            return;
        }
        const tag = listType === 'ol' ? 'ol' : 'ul';
        const startAttr = listType === 'ol' && listStart > 1 ? ` start="${listStart}"` : '';
        html.push(`<${tag}${startAttr}>${listItems.map((item) => `<li>${inline(item)}</li>`).join('')}</${tag}>`);
        listType = null;
        listItems = [];
        listStart = 1;
    };

    for (let index = 0; index < lines.length; index++) {
        const rawLine = lines[index];
        const line = rawLine.trim();
        if (!line) {
            flushParagraph();
            flushList();
            continue;
        }

        // GitHub-style Markdown table. The separator row is the reliable signal,
        // so ordinary sentences containing a pipe remain normal text.
        if (line.includes('|') && index + 1 < lines.length && isTableSeparator(lines[index + 1])) {
            flushParagraph();
            flushList();
            const bodyLines = [];
            index += 2;
            while (index < lines.length) {
                const candidate = lines[index].trim();
                if (!candidate || !candidate.includes('|')) {
                    index -= 1;
                    break;
                }
                bodyLines.push(lines[index]);
                index += 1;
            }
            if (index >= lines.length) {
                index = lines.length;
            }
            html.push(renderTable(rawLine, bodyLines));
            continue;
        }

        const heading = line.match(/^(#{1,3})\s+(.+)$/);
        if (heading) {
            flushParagraph();
            flushList();
            const level = heading[1].length;
            orderedCounter = 0;
            html.push(`<h${level}>${inline(heading[2])}</h${level}>`);
            continue;
        }

        const unordered = line.match(/^[-*]\s+(.+)$/);
        if (unordered) {
            flushParagraph();
            if (listType && listType !== 'ul') {
                flushList();
            }
            listType = 'ul';
            listItems.push(unordered[1]);
            continue;
        }

        const ordered = line.match(/^(\d+)[.)]\s+(.+)$/);
        if (ordered) {
            flushParagraph();
            const sourceNumber = Number.parseInt(ordered[1], 10) || 1;
            if (listType && listType !== 'ol') {
                flushList();
            }
            if (listType !== 'ol') {
                // Some models restart each step at "1." when bullets appear between steps.
                // Keep the visible sequence continuous while still respecting an explicit higher start.
                const nextNumber = orderedCounter > 0 ? orderedCounter + 1 : 1;
                listStart = sourceNumber > nextNumber ? sourceNumber : nextNumber;
                orderedCounter = listStart;
                listType = 'ol';
            } else {
                const nextNumber = orderedCounter + 1;
                orderedCounter = sourceNumber > nextNumber ? sourceNumber : nextNumber;
            }
            listItems.push(ordered[2]);
            continue;
        }

        flushList();
        paragraph.push(line);
    }

    flushParagraph();
    flushList();
    return html.join('');
};

const appendMessage = (container, type, content) => {
    const el = document.createElement('div');
    el.className = `fredkichat-message fredkichat-message-${type}`;
    if (type === 'bot') {
        el.innerHTML = renderMarkdown(content);
    } else {
        el.textContent = content;
    }
    container.appendChild(el);
    container.scrollTop = container.scrollHeight;
    return el;
};

const makeId = () => `t${Date.now().toString(36)}${Math.random().toString(36).slice(2, 9)}`;

export const init = (config) => {
    const root = document.querySelector('[data-region="fredkichat"]');
    if (!root) {
        return;
    }

    const input = root.querySelector('[data-region="input"]');
    const send = root.querySelector('[data-action="send"]');
    const stop = root.querySelector('[data-action="stop"]');
    const stopbar = root.querySelector('[data-region="stopbar"]');
    const messages = root.querySelector('[data-region="messages"]');
    const scroller = root.querySelector('.fredkichat-main');
    const history = root.querySelector('[data-region="history"]');
    const newChat = root.querySelector('[data-action="new-chat"]');
    const toggles = root.querySelectorAll('[data-action="toggle-history"]');
    const thinkingLabel = root.querySelector('[data-region="thinking-label"]')?.textContent || '';
    const str = Object.assign({newchat: '', deletechat: '', error: ''}, config.strings || {});
    const starterQuestions = root.querySelectorAll('[data-action="starter-question"]');

    let activeReader = null;
    let stopping = false;
    let stopTyping = null;

    const scrollToLatest = () => {
        if (!scroller) {
            return;
        }
        window.requestAnimationFrame(() => {
            scroller.scrollTop = scroller.scrollHeight;
        });
    };

    let threads = [];
    try {
        const saved = JSON.parse(sessionStorage.getItem(config.storagekey) || '[]');
        if (Array.isArray(saved)) {
            threads = saved;
        }
    } catch (e) {
        threads = [];
    }
    if (!threads.length) {
        threads = [{id: makeId(), title: str.newchat, messages: []}];
    }
    let activeId = threads[0].id;

    const saveThreads = () => {
        try {
            sessionStorage.setItem(config.storagekey, JSON.stringify(threads.slice(0, 20)));
        } catch (e) {
            // Session storage is optional; chat still works without it.
        }
    };
    const activeThread = () => threads.find((thread) => thread.id === activeId);

    const renderMessages = () => {
        messages.innerHTML = '';
        const thread = activeThread();
        if (!thread) {
            return;
        }
        thread.messages.forEach((item) => appendMessage(messages, item.type, item.content));
        scrollToLatest();
    };

    const deleteThread = (threadid) => {
        const index = threads.findIndex((thread) => thread.id === threadid);
        if (index === -1) {
            return;
        }
        const wasActive = activeId === threadid;
        threads.splice(index, 1);
        if (!threads.length) {
            threads = [{id: makeId(), title: str.newchat, messages: []}];
        }
        if (wasActive || !threads.some((thread) => thread.id === activeId)) {
            activeId = threads[0].id;
        }
        saveThreads();
        renderHistory();
        renderMessages();
    };

    const renderHistory = () => {
        history.innerHTML = '';
        threads.forEach((thread) => {
            const row = document.createElement('div');
            row.className = 'fredkichat-history-row' + (thread.id === activeId ? ' is-active' : '');

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'fredkichat-history-item';
            button.textContent = thread.title || str.newchat;
            button.addEventListener('click', () => {
                activeId = thread.id;
                renderMessages();
                renderHistory();
                root.classList.remove('history-open');
            });

            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'fredkichat-history-delete';
            deleteButton.setAttribute('aria-label', str.deletechat);
            deleteButton.title = str.deletechat;
            deleteButton.innerHTML = '<span aria-hidden="true">🗑</span>';
            deleteButton.addEventListener('click', (event) => {
                event.stopPropagation();
                deleteThread(thread.id);
            });

            row.appendChild(button);
            row.appendChild(deleteButton);
            history.appendChild(row);
        });
    };

    const updateThreadTitle = (thread, message) => {
        if (thread.messages.filter((item) => item.type === 'user').length === 1) {
            thread.title = message.length > 42 ? `${message.slice(0, 39)}…` : message;
        }
    };

    const showThinking = () => {
        const el = document.createElement('div');
        el.className = 'fredkichat-message fredkichat-message-bot fredkichat-loading';
        el.innerHTML = `<span class="fredkichat-thinking"><span class="fredkichat-thinking-text">${escapeHtml(thinkingLabel)}</span><span class="fredkichat-thinking-dot"></span><span class="fredkichat-thinking-dot"></span><span class="fredkichat-thinking-dot"></span></span>`;
        messages.appendChild(el);
        scrollToLatest();
        return el;
    };

    const setGenerating = (isGenerating) => {
        send.disabled = isGenerating;
        input.disabled = isGenerating;
        if (stopbar) {
            stopbar.hidden = !isGenerating;
        }
        if (stop) {
            stop.disabled = !isGenerating;
        }
    };

    const stopGeneration = () => {
        if (!activeReader && !stopTyping) {
            return;
        }
        stopping = true;
        if (activeReader) {
            activeReader.cancel().catch(() => {});
        }
        if (stopTyping) {
            stopTyping();
        }
    };

    const submit = async() => {
        const message = input.value.trim();
        const thread = activeThread();
        if (!message || !thread || send.disabled) {
            return;
        }

        appendMessage(messages, 'user', message);
        scrollToLatest();
        thread.messages.push({type: 'user', content: message});
        updateThreadTitle(thread, message);
        saveThreads();
        renderHistory();
        input.value = '';
        setGenerating(true);
        stopping = false;
        const pending = showThinking();

        // "receivedAnswer" is exactly what arrived from FredKI. "visibleAnswer"
        // is what the user has already seen. Keeping these separate lets us show
        // the real stream immediately but smooth out irregular network packet sizes.
        let receivedAnswer = '';
        let visibleAnswer = '';
        let displayQueue = '';
        let streamEnded = false;
        let abortController = null;
        let resolveDrain = null;
        let drainPromise = null;
        let stopPersisted = false;

        const ensureDrainPromise = () => {
            if (!drainPromise) {
                drainPromise = new Promise((resolve) => {
                    resolveDrain = resolve;
                });
            }
            return drainPromise;
        };

        const finishDrainIfReady = () => {
            if (streamEnded && displayQueue.length === 0 && resolveDrain) {
                const resolve = resolveDrain;
                resolveDrain = null;
                resolve();
            }
        };

        const renderVisible = () => {
            pending.classList.remove('fredkichat-loading', 'fredkichat-streaming-plain');
            // Render the already-visible part as Markdown while the answer is still
            // growing. This lets headings, paragraphs and lists appear progressively
            // instead of only after the stream has finished. The renderer is deliberately
            // tolerant of incomplete Markdown: unfinished constructs stay plain until
            // enough characters have arrived to recognize them.
            pending.innerHTML = renderMarkdown(visibleAnswer);
            scrollToLatest();
        };

        // Visual streaming deliberately runs a little slower than the raw network
        // stream. FredKI/proxies often deliver short bursts followed by a brief gap.
        // If we render those bursts at 60 fps, the UI empties the queue immediately
        // and looks jerky. A paced drip keeps a small visual buffer and makes the
        // answer feel continuous without waiting until the end.
        const DISPLAY_DELAY_MS = 42;
        const LOW_WATERMARK = 6;
        let displayTimer = null;

        const scheduleDisplay = (delay = DISPLAY_DELAY_MS) => {
            if (displayTimer !== null) {
                return;
            }
            displayTimer = window.setTimeout(pumpDisplay, delay);
        };

        const pumpDisplay = () => {
            displayTimer = null;
            if (displayQueue.length === 0) {
                finishDrainIfReady();
                return;
            }

            // While FredKI is still producing text, keep a tiny reserve instead of
            // draining every packet instantly. This bridges short network/proxy gaps.
            if (!streamEnded && displayQueue.length <= LOW_WATERMARK) {
                scheduleDisplay(60);
                return;
            }

            const backlog = displayQueue.length;
            let take = 1;
            let delay = DISPLAY_DELAY_MS;

            // Catch up gently only when a larger server/proxy burst arrived. Even then
            // the text remains visibly incremental instead of appearing sentence-wise.
            if (backlog > 600) {
                take = 5;
                delay = 24;
            } else if (backlog > 320) {
                take = 4;
                delay = 28;
            } else if (backlog > 160) {
                take = 3;
                delay = 32;
            } else if (backlog > 70) {
                take = 2;
                delay = 36;
            }

            visibleAnswer += displayQueue.slice(0, take);
            displayQueue = displayQueue.slice(take);
            renderVisible();

            if (displayQueue.length > 0) {
                scheduleDisplay(delay);
            } else {
                finishDrainIfReady();
            }
        };

        const queueLiveContent = (content) => {
            if (!content) {
                return;
            }
            receivedAnswer += content;
            displayQueue += content;
            scheduleDisplay(displayQueue.length < 12 ? 55 : DISPLAY_DELAY_MS);
        };

        const stopVisualPump = () => {
            if (displayTimer !== null) {
                window.clearTimeout(displayTimer);
                displayTimer = null;
            }
        };

        stopTyping = () => {
            if (stopping) {
                return;
            }

            // Stop the visible answer immediately. Do not wait for fetch()/PHP to
            // notice the cancelled connection; on buffered hosting stacks that can
            // take a moment. Everything the learner has already seen is preserved,
            // while queued but not yet displayed characters are discarded.
            stopping = true;
            stopVisualPump();
            displayQueue = '';
            streamEnded = true;
            finishDrainIfReady();

            if (abortController) {
                abortController.abort();
            }
            if (activeReader) {
                activeReader.cancel().catch(() => {});
            }

            const kept = visibleAnswer || receivedAnswer;
            if (kept) {
                pending.classList.remove('fredkichat-loading', 'fredkichat-streaming-plain');
                pending.innerHTML = renderMarkdown(kept);
                if (!stopPersisted) {
                    thread.messages.push({type: 'bot', content: kept});
                    saveThreads();
                    stopPersisted = true;
                }
            } else {
                pending.remove();
            }

            if (stopbar) {
                stopbar.hidden = true;
            }
            if (stop) {
                stop.disabled = true;
            }
            scrollToLatest();
        };

        try {
            const body = new URLSearchParams();
            if (config.publictoken) {
                body.set('publictoken', config.publictoken);
            } else {
                body.set('sesskey', config.sesskey);
            }
            body.set('botid', config.botid);
            if (config.blockid) {
                body.set('blockid', config.blockid);
            }
            body.set('threadid', thread.id);
            body.set('message', message);

            abortController = new AbortController();
            const response = await fetch(config.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'Accept': 'text/event-stream',
                    'Cache-Control': 'no-cache',
                },
                credentials: 'same-origin',
                body: body.toString(),
                signal: abortController.signal,
            });
            if (!response.ok || !response.body) {
                throw new Error(`HTTP ${response.status}`);
            }

            const reader = response.body.getReader();
            activeReader = reader;
            const decoder = new TextDecoder();
            let buffer = '';
            let done = false;
            let serverDone = false;

            const handleBlock = (block) => {
                const lines = block.split(/\r?\n/);
                for (const line of lines) {
                    if (!line.startsWith('data:')) {
                        continue;
                    }
                    let event;
                    try {
                        event = JSON.parse(line.slice(5).trim());
                    } catch (e) {
                        continue;
                    }
                    if (event.type === 'error') {
                        throw new Error(event.error || 'FredKI request failed');
                    }
                    if (event.type === 'chunk' && typeof event.content === 'string') {
                        // Preserve whitespace exactly. FredKI often sends a leading
                        // blank in a separate chunk and trimming would join words.
                        queueLiveContent(event.content);
                    } else if (event.type === 'done') {
                        serverDone = true;
                    }
                }
            };

            while (!done && !serverDone) {
                const result = await reader.read();
                done = result.done;
                if (result.value) {
                    buffer += decoder.decode(result.value, {stream: !done});
                }
                const blocks = buffer.split(/\r?\n\r?\n/);
                buffer = blocks.pop() || '';
                for (const block of blocks) {
                    handleBlock(block);
                }
            }

            if (buffer.trim()) {
                handleBlock(buffer);
            }

            // reader.cancel() may end a ReadableStream cleanly instead of throwing.
            // Force the stopped path so a cancelled response is never replaced by
            // the complete upstream answer a moment later.
            if (stopping) {
                throw new DOMException('Generation stopped', 'AbortError');
            }

            streamEnded = true;
            ensureDrainPromise();
            finishDrainIfReady();
            await drainPromise;

            if (!receivedAnswer) {
                if (stopping) {
                    pending.remove();
                    return;
                }
                throw new Error('FredKI returned no answer content');
            }

            pending.innerHTML = renderMarkdown(receivedAnswer);
            scrollToLatest();
            thread.messages.push({type: 'bot', content: receivedAnswer});
            saveThreads();
        } catch (e) {
            streamEnded = true;
            stopVisualPump();
            if (e?.name === 'AbortError' || stopping) {
                // stopTyping() already freezes and persists the visible partial answer
                // immediately. Keep this fallback for aborts that happen before the
                // stop handler could do so.
                if (!stopPersisted) {
                    const kept = visibleAnswer || receivedAnswer;
                    if (kept) {
                        pending.classList.remove('fredkichat-loading', 'fredkichat-streaming-plain');
                        pending.innerHTML = renderMarkdown(kept);
                        thread.messages.push({type: 'bot', content: kept});
                        saveThreads();
                        stopPersisted = true;
                    } else if (pending.isConnected) {
                        pending.remove();
                    }
                }
            } else {
                pending.classList.remove('fredkichat-loading', 'fredkichat-streaming-plain');
                pending.textContent = str.error;
                // eslint-disable-next-line no-console
                console.error(e);
            }
        } finally {
            stopVisualPump();
            if (activeReader) {
                try {
                    await activeReader.cancel();
                } catch (e) {
                    // Reader may already be closed.
                }
            }
            activeReader = null;
            abortController = null;
            stopTyping = null;
            stopping = false;
            setGenerating(false);
            input.focus();
            scrollToLatest();
        }
    };

    newChat.addEventListener('click', () => {
        const thread = {id: makeId(), title: str.newchat, messages: []};
        threads.unshift(thread);
        activeId = thread.id;
        saveThreads();
        renderHistory();
        renderMessages();
        root.classList.remove('history-open');
        input.focus();
    });
    toggles.forEach((toggle) => toggle.addEventListener('click', () => root.classList.toggle('history-open')));
    starterQuestions.forEach((button) => button.addEventListener('click', () => {
        const question = button.dataset.question || button.textContent || '';
        input.value = question.trim();
        submit();
    }));
    send.addEventListener('click', submit);
    if (stop) {
        stop.addEventListener('click', stopGeneration);
    }
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            submit();
        }
    });

    renderHistory();
    renderMessages();
};
