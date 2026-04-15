import { getDocument } from 'pdfjs-dist/webpack.mjs';

const PREVIEW_SELECTOR = '[data-siardi-pdf-preview]';
const instances = new WeakMap();

class SiardiPdfPreview {
    constructor(root) {
        this.root = root;
        this.src = root.dataset.src ?? '';
        this.viewport = root.querySelector('[data-role="viewport"]');
        this.canvas = root.querySelector('[data-role="canvas"]');
        this.loadingState = root.querySelector('[data-role="loading"]');
        this.errorState = root.querySelector('[data-role="error"]');
        this.pageIndicator = root.querySelector('[data-role="page-indicator"]');
        this.zoomIndicator = root.querySelector('[data-role="zoom-indicator"]');
        this.prevButton = root.querySelector('[data-action="prev"]');
        this.nextButton = root.querySelector('[data-action="next"]');
        this.zoomOutButton = root.querySelector('[data-action="zoom-out"]');
        this.zoomInButton = root.querySelector('[data-action="zoom-in"]');
        this.fitWidthButton = root.querySelector('[data-action="fit-width"]');
        this.resizeObserver = null;
        this.loadingTask = null;
        this.renderTask = null;
        this.pdfDocument = null;
        this.pageNumber = 1;
        this.totalPages = 0;
        this.scale = 1;
        this.scaleMode = 'fit-width';
        this.hasRenderedPage = false;
        this.renderToken = 0;
        this.lastViewportWidth = 0;
        this.isBooted = false;
    }

    init() {
        if (this.isBooted || !this.src || !this.viewport || !this.canvas) {
            return;
        }

        this.isBooted = true;
        this.bindEvents();
        this.observeResize();
        this.updateUi();
        void this.loadDocument();
    }

    bindEvents() {
        this.prevButton?.addEventListener('click', () => {
            if (this.pageNumber <= 1) {
                return;
            }

            this.pageNumber -= 1;
            void this.renderPage();
        });

        this.nextButton?.addEventListener('click', () => {
            if (this.pageNumber >= this.totalPages) {
                return;
            }

            this.pageNumber += 1;
            void this.renderPage();
        });

        this.zoomOutButton?.addEventListener('click', () => {
            this.scaleMode = 'manual';
            this.scale = Math.max(this.scale / 1.2, 0.25);
            void this.renderPage();
        });

        this.zoomInButton?.addEventListener('click', () => {
            this.scaleMode = 'manual';
            this.scale = Math.min(this.scale * 1.2, 5);
            void this.renderPage();
        });

        this.fitWidthButton?.addEventListener('click', () => {
            this.scaleMode = 'fit-width';
            void this.renderPage({ forceFit: true });
        });
    }

    observeResize() {
        if (typeof ResizeObserver === 'undefined') {
            return;
        }

        this.resizeObserver = new ResizeObserver((entries) => {
            const entry = entries[0];
            const width = Math.round(entry?.contentRect?.width ?? this.viewport?.clientWidth ?? 0);

            if (width < 32 || width === this.lastViewportWidth) {
                return;
            }

            this.lastViewportWidth = width;

            if (!this.pdfDocument) {
                return;
            }

            if (!this.hasRenderedPage || this.scaleMode === 'fit-width') {
                void this.renderPage({ forceFit: true });
            }
        });

        this.resizeObserver.observe(this.viewport);
    }

    async loadDocument() {
        this.showLoading('Memuat preview PDF...');
        this.hideError();

        try {
            const task = getDocument(this.src);
            this.loadingTask = task;
            this.pdfDocument = await task.promise;
            this.totalPages = this.pdfDocument.numPages;
            this.pageNumber = 1;
            await this.renderPage({ forceFit: true });
        } catch (error) {
            console.error('Failed to load PDF preview.', error);
            this.showError('Preview PDF gagal dimuat. Gunakan link di bawah untuk membuka file.');
            this.updateUi();
        }
    }

    async renderPage({ forceFit = false } = {}) {
        if (!this.pdfDocument || !this.viewport || !this.canvas) {
            return;
        }

        const availableWidth = Math.round(this.viewport.clientWidth);

        if (availableWidth < 32) {
            this.showLoading('Menyiapkan area preview PDF...');
            return;
        }

        this.lastViewportWidth = availableWidth;

        if (this.renderTask) {
            try {
                this.renderTask.cancel();
            } catch {
                // Ignore cancelled render lifecycle.
            }
        }

        const renderToken = ++this.renderToken;

        try {
            const page = await this.pdfDocument.getPage(this.pageNumber);

            if (renderToken !== this.renderToken) {
                return;
            }

            const baseViewport = page.getViewport({ scale: 1 });

            if (forceFit || !this.hasRenderedPage || this.scaleMode === 'fit-width') {
                this.scale = Math.max(availableWidth / baseViewport.width, 0.25);
                this.scaleMode = 'fit-width';
            }

            this.scale = Math.min(Math.max(this.scale, 0.25), 5);

            const viewport = page.getViewport({ scale: this.scale });
            const outputScale = window.devicePixelRatio || 1;
            const context = this.canvas.getContext('2d', { alpha: false });

            if (!context) {
                throw new Error('Canvas context unavailable.');
            }

            this.canvas.width = Math.ceil(viewport.width * outputScale);
            this.canvas.height = Math.ceil(viewport.height * outputScale);
            this.canvas.style.width = `${viewport.width}px`;
            this.canvas.style.height = `${viewport.height}px`;

            context.clearRect(0, 0, this.canvas.width, this.canvas.height);

            this.showLoading('Merender preview PDF...');
            this.hideError();

            const transform = outputScale === 1
                ? null
                : [outputScale, 0, 0, outputScale, 0, 0];

            const renderTask = page.render({
                canvasContext: context,
                transform,
                viewport,
            });

            this.renderTask = renderTask;
            await renderTask.promise;

            if (renderToken !== this.renderToken) {
                return;
            }

            this.hasRenderedPage = true;
            this.canvas.hidden = false;
            this.hideLoading();
            this.updateUi();
        } catch (error) {
            if (error?.name === 'RenderingCancelledException') {
                return;
            }

            console.error('Failed to render PDF preview.', error);
            this.showError('Preview PDF gagal dirender. Gunakan link di bawah untuk membuka file.');
            this.updateUi();
        } finally {
            this.renderTask = null;
        }
    }

    updateUi() {
        if (this.pageIndicator) {
            this.pageIndicator.textContent = `Halaman ${this.pageNumber} / ${this.totalPages || '--'}`;
        }

        if (this.zoomIndicator) {
            this.zoomIndicator.textContent = `${Math.round(this.scale * 100)}%`;
        }

        if (this.prevButton) {
            this.prevButton.disabled = !this.pdfDocument || this.pageNumber <= 1;
        }

        if (this.nextButton) {
            this.nextButton.disabled = !this.pdfDocument || this.pageNumber >= this.totalPages;
        }

        const canZoom = Boolean(this.pdfDocument);

        if (this.zoomOutButton) {
            this.zoomOutButton.disabled = !canZoom;
        }

        if (this.zoomInButton) {
            this.zoomInButton.disabled = !canZoom;
        }

        if (this.fitWidthButton) {
            this.fitWidthButton.disabled = !canZoom;
        }
    }

    showLoading(message) {
        if (!this.loadingState) {
            return;
        }

        this.loadingState.textContent = message;
        this.loadingState.hidden = false;
    }

    hideLoading() {
        if (!this.loadingState) {
            return;
        }

        this.loadingState.hidden = true;
    }

    showError(message) {
        if (this.errorState) {
            this.errorState.textContent = message;
            this.errorState.hidden = false;
        }

        if (this.canvas) {
            this.canvas.hidden = true;
        }

        this.hideLoading();
    }

    hideError() {
        if (!this.errorState) {
            return;
        }

        this.errorState.hidden = true;
    }
}

function mountPreview(root) {
    if (!(root instanceof HTMLElement) || instances.has(root)) {
        return;
    }

    const instance = new SiardiPdfPreview(root);
    instances.set(root, instance);
    instance.init();
}

function hydratePreviews(root = document) {
    if (root instanceof HTMLElement && root.matches(PREVIEW_SELECTOR)) {
        mountPreview(root);
    }

    root.querySelectorAll?.(PREVIEW_SELECTOR).forEach((node) => {
        mountPreview(node);
    });
}

function observePreviews() {
    if (typeof MutationObserver === 'undefined' || !document.body) {
        return;
    }

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach((node) => {
                if (!(node instanceof HTMLElement)) {
                    return;
                }

                hydratePreviews(node);
            });
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
}

function bootPdfPreviews() {
    hydratePreviews(document);
    observePreviews();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootPdfPreviews, { once: true });
} else {
    bootPdfPreviews();
}

document.addEventListener('livewire:navigated', () => {
    hydratePreviews(document);
});
