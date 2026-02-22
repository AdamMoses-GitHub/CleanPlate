/**
 * CleanPlate - Recipe Extraction Client
 * State management and API communication
 */

// Recipe state object
const RecipeState = {
    currentRecipe: null,
    isLoading: false,
    lastError: null,

    setRecipe(recipe) {
        this.currentRecipe = recipe;
    },

    getRecipe() {
        return this.currentRecipe;
    },

    setLoading(loading) {
        this.isLoading = loading;
    },

    setError(error) {
        this.lastError = error;
    },

    reset() {
        this.currentRecipe = null;
        this.isLoading = false;
        this.lastError = null;
    }
};

// Custom error class for recipe extraction
class RecipeError extends Error {
    constructor(code, userMessage, suggestions = []) {
        super(userMessage);
        this.name = 'RecipeError';
        this.code = code;
        this.suggestions = suggestions;
    }
}

// Image Carousel Controller
class ImageCarousel {
    constructor(elements) {
        this.elements = elements;
        this.images = [];
        this.currentIndex = 0;
        this.recipeUrl = null;
        
        this.bindEvents();
    }

    bindEvents() {
        this.elements.carouselPrev.addEventListener('click', () => this.prev());
        this.elements.carouselNext.addEventListener('click', () => this.next());
        this.elements.carouselConfirm.addEventListener('click', () => this.selectCurrent());
        this.elements.carouselHide.addEventListener('click', () => this.hideImage());

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (this.elements.imageCarousel.style.display !== 'none') {
                if (e.key === 'ArrowLeft') this.prev();
                if (e.key === 'ArrowRight') this.next();
                if (e.key === 'Enter') this.selectCurrent();
            }
        });
    }

    init(imageCandidates, recipeUrl, autoShow = true) {
        if (!imageCandidates || imageCandidates.length <= 1) {
            this.hide();
            return false;
        }

        this.images = imageCandidates;
        this.recipeUrl = recipeUrl;
        
        // Check localStorage for saved preference
        const savedIndex = this.getSavedImageIndex();
        this.currentIndex = savedIndex !== null ? savedIndex : 0;

        if (autoShow) {
            this.show();
        }
        return true;
    }

    show() {
        if (this.images.length === 0) return;

        // Hide image container and hidden banner
        this.elements.recipeImageContainer.style.display = 'none';
        this.elements.imageHiddenBanner.style.display = 'none';
        
        // Show carousel
        this.elements.imageCarousel.style.display = 'block';
        this.updateDisplay();
    }

    hide() {
        this.elements.imageCarousel.style.display = 'none';
    }

    updateDisplay() {
        const current = this.images[this.currentIndex];
        
        // Update image
        this.elements.carouselImage.src = current.url;
        this.elements.carouselImage.alt = current.alt || 'Recipe image candidate';
        
        // Update counter
        this.elements.carouselCounter.textContent = `${this.currentIndex + 1} / ${this.images.length}`;
        
        // Update button states
        this.elements.carouselPrev.disabled = this.currentIndex === 0;
        this.elements.carouselNext.disabled = this.currentIndex === this.images.length - 1;
    }

    next() {
        if (this.currentIndex < this.images.length - 1) {
            this.currentIndex++;
            this.updateDisplay();
        }
    }

    prev() {
        if (this.currentIndex > 0) {
            this.currentIndex--;
            this.updateDisplay();
        }
    }

    selectCurrent() {
        const selected = this.images[this.currentIndex];
        
        // Save preference to localStorage
        this.saveImagePreference(this.currentIndex);
        
        // Update main recipe image
        this.elements.recipeImage.src = selected.url;
        this.elements.recipeImage.alt = selected.alt || 'Recipe';
        this.elements.recipeImageContainer.style.display = 'block';
        this.elements.imageHiddenBanner.style.display = 'none';
        
        // Hide carousel
        this.hide();
        
        // Show toast notification
        if (UI.showToast) {
            UI.showToast('Image updated!', 2000);
        }
    }

    hideImage() {
        // Save "no image" preference to localStorage
        this.saveNoImagePreference();
        
        // Hide the image container and show banner
        this.elements.recipeImageContainer.style.display = 'none';
        this.elements.imageHiddenBanner.style.display = 'flex';
        
        // Hide carousel
        this.hide();
        
        // Show toast notification
        if (UI.showToast) {
            UI.showToast('Image hidden', 2000);
        }
    }

    saveImagePreference(index) {
        if (!this.recipeUrl) return;
        
        try {
            const key = `cleanplate_image_${this.getUrlHash(this.recipeUrl)}`;
            const preference = {
                index: index,
                url: this.images[index].url,
                hidden: false,
                timestamp: Date.now()
            };
            localStorage.setItem(key, JSON.stringify(preference));
        } catch (e) {
            console.warn('Failed to save image preference:', e);
        }
    }

    saveNoImagePreference() {
        if (!this.recipeUrl) return;
        
        try {
            const key = `cleanplate_image_${this.getUrlHash(this.recipeUrl)}`;
            const preference = {
                hidden: true,
                timestamp: Date.now()
            };
            localStorage.setItem(key, JSON.stringify(preference));
        } catch (e) {
            console.warn('Failed to save no image preference:', e);
        }
    }

    getSavedImageIndex() {
        if (!this.recipeUrl) return null;
        
        try {
            const key = `cleanplate_image_${this.getUrlHash(this.recipeUrl)}`;
            const saved = localStorage.getItem(key);
            
            if (saved) {
                const preference = JSON.parse(saved);
                
                // Check if preference is less than 30 days old
                const age = Date.now() - preference.timestamp;
                const maxAge = 30 * 24 * 60 * 60 * 1000; // 30 days in ms
                
                if (age < maxAge) {
                    if (preference.hidden) {
                        return 'hidden';
                    }
                    if (preference.index < this.images.length) {
                        return preference.index;
                    }
                }
            }
        } catch (e) {
            console.warn('Failed to load image preference:', e);
        }
        
        return null;
    }

    getUrlHash(url) {
        // Simple hash function for URL
        let hash = 0;
        for (let i = 0; i < url.length; i++) {
            const char = url.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32bit integer
        }
        return Math.abs(hash).toString(36);
    }
}

// Video Modal Controller
class VideoModal {
    constructor() {
        this.modal = document.getElementById('video-modal');
        this.backdrop = this.modal.querySelector('.video-modal-backdrop');
        this.closeBtn = this.modal.querySelector('.video-modal-close');
        this.playerContainer = document.getElementById('video-modal-player');
        this.videoData = null;
        
        this.bindEvents();
    }

    bindEvents() {
        // Close button
        this.closeBtn.addEventListener('click', () => this.close());
        
        // Click backdrop to close
        this.backdrop.addEventListener('click', () => this.close());
        
        // ESC key to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.style.display !== 'none') {
                this.close();
            }
        });
    }

    open(videoData) {
        this.videoData = videoData;
        this.renderVideo();
        this.modal.style.display = 'block';
        document.body.style.overflow = 'hidden'; // Prevent scrolling
    }

    close() {
        this.modal.style.display = 'none';
        document.body.style.overflow = ''; // Restore scrolling
        this.playerContainer.innerHTML = ''; // Clear video to stop playback
    }

    renderVideo() {
        const { url, platform } = this.videoData;
        let html = '';

        switch (platform) {
            case 'youtube':
                html = `<iframe src="${this.escapeHtml(url)}" 
                    frameborder="0" 
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                    allowfullscreen></iframe>`;
                break;

            case 'vimeo':
                html = `<iframe src="${this.escapeHtml(url)}" 
                    frameborder="0" 
                    allow="autoplay; fullscreen; picture-in-picture" 
                    allowfullscreen></iframe>`;
                break;

            case 'html5':
                html = `<video controls preload="metadata">
                    <source src="${this.escapeHtml(url)}" type="video/mp4">
                    Your browser does not support the video tag.
                </video>`;
                break;

            case 'external':
            default:
                // For external/unknown platforms, open in new tab
                window.open(url, '_blank', 'noopener,noreferrer');
                this.close();
                return;
        }

        this.playerContainer.innerHTML = html;
    }

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
}

// API Client
class CleanPlateAPI {
    constructor(endpoint = './api/parser.php') {
        this.endpoint = endpoint;
    }

    async parseRecipe(url) {
        try {
            const response = await fetch(this.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ url })
            });

            const data = await response.json();

            if (data.status === 'error') {
                throw new RecipeError(data.code, data.userMessage, data.suggestions || []);
            }

            return data;

        } catch (error) {
            if (error instanceof RecipeError) {
                throw error;
            }
            
            // Network or parsing error
            throw new RecipeError(
                'NETWORK_ERROR',
                'Unable to connect to the server. Please check your connection and try again.',
                []
            );
        }
    }
}

// UI Controller
const UI = {
    elements: {
        form: document.getElementById('parse-form'),
        urlInput: document.getElementById('url-input'),
        parseBtn: document.getElementById('parse-btn'),
        btnText: document.querySelector('.btn-text'),
        btnLoading: document.querySelector('.btn-loading'),
        landingView: document.getElementById('landing-view'),
        errorContainer: document.getElementById('error-message'),
        recipeDisplay: document.getElementById('recipe-display'),
        backButton: document.getElementById('back-button'),
        confidenceContainer: document.getElementById('confidence-container'),
        confidenceBadge: document.getElementById('confidence-badge'),
        confidenceToggle: document.getElementById('confidence-toggle'),
        confidenceDetails: document.getElementById('confidence-details'),
        confidenceDetailsBody: document.getElementById('confidence-details-body'),
        recipeTitle: document.getElementById('recipe-title'),
        titleEditBtn: document.getElementById('title-edit-btn'),
        recipeMetadata: document.getElementById('recipe-metadata'),
        recipeSource: document.getElementById('recipe-source'),
        recipeSourceLink: document.getElementById('recipe-source-link'),
        ingredientsList: document.getElementById('ingredients-list'),
        instructionsList: document.getElementById('instructions-list'),
        copyIngredientsBtn: document.getElementById('copy-ingredients-btn'),
        copyInstructionsBtn: document.getElementById('copy-instructions-btn'),
        printBtn: document.getElementById('print-btn'),
        recipeImageContainer: document.getElementById('recipe-image-container'),
        recipeImage: document.getElementById('recipe-image'),
        imageSelectorBtn: document.getElementById('image-selector-btn'),
        imageHiddenBanner: document.getElementById('image-hidden-banner'),
        imageHiddenChange: document.getElementById('image-hidden-change'),
        imageCarousel: document.getElementById('image-carousel'),
        carouselImage: document.getElementById('carousel-image'),
        carouselCounter: document.getElementById('carousel-counter'),
        carouselPrev: document.getElementById('carousel-prev'),
        carouselNext: document.getElementById('carousel-next'),
        carouselConfirm: document.getElementById('carousel-confirm'),
        carouselHide: document.getElementById('carousel-hide'),
        toast: document.getElementById('toast-notification'),
        viewSelectorBtn: document.getElementById('view-selector-btn'),
        viewSelectorLabel: document.getElementById('view-selector-label'),
        viewDropdownMenu: document.getElementById('view-dropdown-menu')
    },

    currentView: 'clean',

    showLoading() {
        this.elements.btnText.style.display = 'none';
        this.elements.btnLoading.style.display = 'inline';
        this.elements.parseBtn.disabled = true;
    },

    hideLoading() {
        this.elements.btnText.style.display = 'inline';
        this.elements.btnLoading.style.display = 'none';
        this.elements.parseBtn.disabled = false;
    },

    showError(message, suggestions = []) {
        this.elements.errorContainer.innerHTML = `
            <div class="error">
                <p>${this.escapeHtml(message)}</p>
                ${suggestions.length ? `
                    <ul class="suggestions">
                        ${suggestions.map(s => `<li>${this.escapeHtml(s)}</li>`).join('')}
                    </ul>
                ` : ''}
            </div>
        `;
        this.elements.errorContainer.style.display = 'block';
        this.elements.recipeDisplay.style.display = 'none';
    },

    hideError() {
        this.elements.errorContainer.style.display = 'none';
    },

    showToast(message, duration = 4000) {
        this.elements.toast.textContent = message;
        this.elements.toast.style.display = 'block';

        setTimeout(() => {
            this.elements.toast.style.display = 'none';
        }, duration);
    },

    setView(viewName) {
        // Validate view name
        const validViews = ['clean', 'extra-info', 'text-only', 'print'];
        if (!validViews.includes(viewName)) {
            viewName = 'clean';
        }

        // Update current view
        this.currentView = viewName;

        // Update body data attribute
        document.body.setAttribute('data-view', viewName);

        // Update dropdown label
        const viewLabels = {
            'clean': 'Clean',
            'extra-info': 'Extra Info',
            'text-only': 'Simple Text',
            'print': 'Printable'
        };
        if (this.elements.viewSelectorLabel) {
            this.elements.viewSelectorLabel.textContent = `View: ${viewLabels[viewName]}`;
        }

        // Close dropdown
        this.closeViewDropdown();

        // Save preference to localStorage
        this.saveViewPreference(viewName);
    },

    toggleViewDropdown() {
        if (this.elements.viewDropdownMenu.style.display === 'none') {
            this.elements.viewDropdownMenu.style.display = 'block';
            this.elements.viewSelectorBtn.classList.add('open');
        } else {
            this.closeViewDropdown();
        }
    },

    closeViewDropdown() {
        if (this.elements.viewDropdownMenu) {
            this.elements.viewDropdownMenu.style.display = 'none';
        }
        if (this.elements.viewSelectorBtn) {
            this.elements.viewSelectorBtn.classList.remove('open');
        }
    },

    saveViewPreference(viewName) {
        try {
            localStorage.setItem('cleanplate_view_preference', viewName);
        } catch (e) {
            console.warn('Failed to save view preference:', e);
        }
    },

    loadViewPreference() {
        try {
            const saved = localStorage.getItem('cleanplate_view_preference');
            if (saved && ['clean', 'extra-info', 'text-only', 'print'].includes(saved)) {
                return saved;
            }
        } catch (e) {
            console.warn('Failed to load view preference:', e);
        }
        return 'clean';
    },

    initializeView() {
        const savedView = this.loadViewPreference();
        // Set view without showing toast on initial load
        const validViews = ['clean', 'extra-info', 'text-only', 'print'];
        const viewName = validViews.includes(savedView) ? savedView : 'clean';
        
        this.currentView = viewName;
        document.body.setAttribute('data-view', viewName);
        
        // Update dropdown label
        const viewLabels = {
            'clean': 'Clean',
            'extra-info': 'Extra Info',
            'text-only': 'Simple Text',
            'print': 'Printable'
        };
        if (this.elements.viewSelectorLabel) {
            this.elements.viewSelectorLabel.textContent = `View: ${viewLabels[viewName]}`;
        }
    },

    showRecipe(recipeData) {
        const { data, phase, confidence, confidenceLevel, confidenceDetails } = recipeData;

        // Store original data for reference
        this.originalRecipeTitle = data.title;
        this.currentRecipeUrl = data.source ? data.source.url : null;

        // Hide landing view, show recipe view
        this.elements.landingView.style.display = 'none';
        this.elements.recipeDisplay.style.display = 'block';
        this.elements.recipeDisplay.classList.add('active');

        // Show confidence badge with score
        // Use confidenceLevel if available, fallback to phase-based detection
        const level = confidenceLevel || (phase === 1 ? 'high' : 'medium');
        const score = confidence || null;
        this.showConfidenceBadge(level, score, confidenceDetails);

        // Show fallback toast if Phase 2 was used
        if (phase === 2) {
            this.showToast('Using deep-scan mode for this site.');
        }

        // Set title
        const savedTitle = this.getSavedTitle(this.currentRecipeUrl);
        this.elements.recipeTitle.textContent = savedTitle || data.title || 'Untitled Recipe';

        // Set source link
        if (data.source && data.source.url) {
            const author = data.source.author || null;
            this.renderSourceLink(data.source.url, author);
        } else {
            this.elements.recipeSource.style.display = 'none';
        }

        // Set metadata if available
        if (data.metadata) {
            this.renderMetadata(data.metadata);
            this.renderDescription(data.metadata.description);
            this.renderDietaryBadges(data.metadata.dietaryInfo);
            this.renderDifficulty(data.metadata.difficulty);
            this.renderVideoButton(data.metadata.video);
            this.renderTaxonomy(data.metadata);
            this.renderRating(data.metadata.rating);
        } else {
            this.elements.recipeMetadata.innerHTML = '';
            this.elements.recipeMetadata.style.display = 'none';
            document.getElementById('recipe-description').style.display = 'none';
            document.getElementById('dietary-badges').style.display = 'none';
            document.getElementById('recipe-difficulty').style.display = 'none';
            document.getElementById('video-btn').style.display = 'none';
            document.getElementById('recipe-taxonomy').style.display = 'none';
            document.getElementById('recipe-rating').style.display = 'none';
        }

        // Render ingredients
        this.renderIngredients(data.ingredients || []);

        // Render instructions
        this.renderInstructions(data.instructions || []);

        // Render image with carousel support
        const imageCandidates = data.metadata && data.metadata.imageCandidates ? data.metadata.imageCandidates : [];
        const primaryImage = data.metadata && data.metadata.imageUrl ? data.metadata.imageUrl : null;
        
        if (imageCandidates.length > 1) {
            // Multiple candidates - auto-select best (first) and show selector button
            const recipeUrl = data.source ? data.source.url : '';
            const carousel = new ImageCarousel(this.elements);
            carousel.init(imageCandidates, recipeUrl, false); // Don't auto-show carousel
            
            // Check for saved preference
            const savedPreference = carousel.getSavedImageIndex();
            
            if (savedPreference === 'hidden') {
                // User chose to hide image - show placeholder banner
                this.elements.recipeImageContainer.style.display = 'none';
                this.elements.imageHiddenBanner.style.display = 'flex';
            } else {
                // Show selected image or default to first
                const selectedImage = savedPreference !== null ? imageCandidates[savedPreference] : imageCandidates[0];
                this.renderImage(selectedImage.url, selectedImage.alt);
                this.elements.imageSelectorBtn.style.display = 'block';
            }
            
            // Store carousel instance for selector button
            this.currentCarousel = carousel;
        } else if (imageCandidates.length === 1) {
            // Single candidate - display directly
            this.renderImage(imageCandidates[0].url, imageCandidates[0].alt);
        } else if (primaryImage) {
            // Fallback to primary image from metadata
            this.renderImage(primaryImage);
        } else {
            // No image available
            this.elements.recipeImageContainer.style.display = 'none';
            this.elements.imageCarousel.style.display = 'none';
        }

        // Show recipe display
        this.elements.recipeDisplay.style.display = 'block';
        this.elements.errorContainer.style.display = 'none';

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    hideRecipe() {
        this.elements.recipeDisplay.style.display = 'none';
        this.elements.recipeDisplay.classList.remove('active');
        this.elements.landingView.style.display = 'block';
        this.elements.urlInput.value = '';
        this.elements.urlInput.focus();
        
        // Reset confidence details panel
        this.elements.confidenceDetails.style.display = 'none';
        this.elements.confidenceToggle.classList.remove('open');
        
        // Reset title editing state
        this.elements.recipeTitle.contentEditable = 'false';
        this.elements.recipeTitle.classList.remove('editing');
        this.originalRecipeTitle = null;
        this.currentRecipeUrl = null;
        
        // Reset image display state
        this.elements.imageCarousel.style.display = 'none';
        this.elements.recipeImageContainer.style.display = 'none';
        this.elements.recipeImage.src = '';
        this.elements.imageSelectorBtn.style.display = 'none';
        this.elements.imageHiddenBanner.style.display = 'none';
        this.currentCarousel = null;
        
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    showConfidenceBadge(level, score = null, details = null) {
        const badge = this.elements.confidenceBadge;
        const container = this.elements.confidenceContainer;
        badge.className = 'confidence-badge';
        
        let badgeText = '';
        let tooltipText = '';
        
        // Determine badge text with score if available
        if (level === 'high' || level === 1) {
            badge.classList.add('high');
            badgeText = score ? `High Confidence (${score}/100)` : 'High confidence extraction';
            container.style.display = 'flex';
        } else if (level === 'medium' || level === 2) {
            badge.classList.add('medium');
            badgeText = score ? `Medium Confidence (${score}/100)` : 'Medium confidence extraction - please verify details';
            container.style.display = 'flex';
        } else if (level === 'low') {
            badge.classList.add('low');
            badgeText = score ? `Low Confidence (${score}/100)` : 'Low confidence extraction - please verify all details';
            container.style.display = 'flex';
        } else {
            container.style.display = 'none';
            return;
        }
        
        badge.textContent = badgeText;
        
        // Build tooltip with detailed breakdown if details are available
        if (details) {
            const parts = [];
            
            // Title
            if (details.title) {
                const titleStatus = details.title.points > 0 ? '✓' : '✗';
                parts.push(`Title: ${titleStatus}`);
            }
            
            // Ingredients
            if (details.ingredients) {
                parts.push(`Ingredients: ${details.ingredients.count}`);
            }
            
            // Instructions
            if (details.instructions) {
                parts.push(`Instructions: ${details.instructions.count}`);
            }
            
            // Metadata
            if (details.metadata) {
                parts.push(`Metadata: ${details.metadata.fieldsPresent}/${details.metadata.fieldsTotal}`);
            }
            
            // Phase
            if (details.phase) {
                parts.push(`Phase: ${details.phase.value}`);
            }
            
            tooltipText = parts.join(', ');
            badge.title = tooltipText;
            
            // Populate the details panel
            this.populateConfidenceDetails(details, score);
        } else {
            badge.title = '';
        }
    },

    populateConfidenceDetails(details, totalScore) {
        const tbody = this.elements.confidenceDetailsBody;
        tbody.innerHTML = '';
        
        // Phase
        if (details.phase) {
            const row = tbody.insertRow();
            row.innerHTML = `
                <td>Extraction Phase</td>
                <td>${details.phase.points} / ${details.phase.max}</td>
                <td><span class="status-icon">Phase ${details.phase.value}</span></td>
            `;
        }
        
        // Title
        if (details.title) {
            const status = details.title.points > 0 ? '✓ Valid' : '✗ Missing/Generic';
            const row = tbody.insertRow();
            row.innerHTML = `
                <td>Recipe Title</td>
                <td>${details.title.points} / ${details.title.max}</td>
                <td><span class="status-icon">${this.escapeHtml(status)}</span></td>
            `;
        }
        
        // Ingredients
        if (details.ingredients) {
            const qualityBonus = details.ingredients.qualityBonus || 0;
            const bonusText = qualityBonus > 0 ? ` (+${qualityBonus} quality)` : '';
            const row = tbody.insertRow();
            row.innerHTML = `
                <td>Ingredients</td>
                <td>${details.ingredients.points} / ${details.ingredients.max}</td>
                <td><span class="status-icon">${details.ingredients.count} items${bonusText}</span></td>
            `;
        }
        
        // Instructions
        if (details.instructions) {
            const qualityBonus = details.instructions.qualityBonus || 0;
            const bonusText = qualityBonus > 0 ? ` (+${qualityBonus} quality)` : '';
            const row = tbody.insertRow();
            row.innerHTML = `
                <td>Instructions</td>
                <td>${details.instructions.points} / ${details.instructions.max}</td>
                <td><span class="status-icon">${details.instructions.count} steps${bonusText}</span></td>
            `;
        }
        
        // Metadata
        if (details.metadata) {
            const row = tbody.insertRow();
            row.innerHTML = `
                <td>Metadata</td>
                <td>${details.metadata.points} / ${details.metadata.max}</td>
                <td><span class="status-icon">${details.metadata.fieldsPresent}/${details.metadata.fieldsTotal} fields</span></td>
            `;
        }
        
        // Total
        if (totalScore) {
            const row = tbody.insertRow();
            row.style.fontWeight = '600';
            row.style.borderTop = '2px solid #e5e7eb';
            row.innerHTML = `
                <td>Total Score</td>
                <td>${totalScore} / 100</td>
                <td></td>
            `;
        }
    },

    toggleConfidenceDetails() {
        const details = this.elements.confidenceDetails;
        const toggle = this.elements.confidenceToggle;
        
        if (details.style.display === 'none') {
            details.style.display = 'block';
            toggle.classList.add('open');
            toggle.title = 'Hide details';
        } else {
            details.style.display = 'none';
            toggle.classList.remove('open');
            toggle.title = 'Show details';
        }
    },

    renderMetadata(metadata) {
        const items = [];
        
        const clockIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>`;
        
        if (metadata.prepTime) {
            items.push({ icon: clockIcon, value: `Prep: ${metadata.prepTime}` });
        }
        if (metadata.cookTime) {
            items.push({ icon: clockIcon, value: `Cook: ${metadata.cookTime}` });
        }
        if (metadata.totalTime) {
            items.push({ icon: clockIcon, value: `Total: ${metadata.totalTime}` });
        }
        if (metadata.servings) {
            const servingIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>`;
            items.push({ icon: servingIcon, value: `Servings: ${metadata.servings}` });
        }

        if (items.length === 0) {
            this.elements.recipeMetadata.style.display = 'none';
            return;
        }

        this.elements.recipeMetadata.style.display = 'flex';
        this.elements.recipeMetadata.innerHTML = items.map(item => `
            <div class="metadata-item">
                ${item.icon}
                <span class="metadata-value">${this.escapeHtml(item.value)}</span>
            </div>
        `).join('');
    },

    renderIngredients(ingredients) {
        if (!Array.isArray(ingredients) || ingredients.length === 0) {
            this.elements.ingredientsList.innerHTML = '<li><span class="ingredient-checkbox"></span><span class="ingredient-text">No ingredients found</span></li>';
            return;
        }

        this.elements.ingredientsList.innerHTML = ingredients.map((ingredient, index) => {
            // Handle both string and object formats
            const text = typeof ingredient === 'string' 
                ? ingredient 
                : `${ingredient.quantity || ''} ${ingredient.unit || ''} ${ingredient.item || ''}`.trim();
            
            return `
                <li>
                    <span class="ingredient-checkbox"></span>
                    <span class="ingredient-text">${this.escapeHtml(text)}</span>
                </li>
            `;
        }).join('');
    },

    renderInstructions(instructions) {
        if (!Array.isArray(instructions) || instructions.length === 0) {
            this.elements.instructionsList.innerHTML = '<li><span class="instruction-number"></span><span class="instruction-text">No instructions found</span></li>';
            return;
        }

        this.elements.instructionsList.innerHTML = instructions.map(instruction => {
            const text = typeof instruction === 'string' 
                ? instruction 
                : instruction.text || '';
            
            return `
                <li>
                    <span class="instruction-number"></span>
                    <span class="instruction-text">${this.escapeHtml(text)}</span>
                </li>
            `;
        }).join('');
    },

    renderImage(imageUrl, altText = null) {
        this.elements.recipeImage.src = imageUrl;
        this.elements.recipeImage.alt = altText || this.elements.recipeTitle.textContent || 'Recipe';
        this.elements.recipeImageContainer.style.display = 'block';
    },

    renderSourceLink(url, author = null) {
        try {
            const urlObj = new URL(url);
            const domain = urlObj.hostname.replace(/^www\./, '');
            
            this.elements.recipeSourceLink.href = url;
            this.elements.recipeSourceLink.textContent = domain;
            
            // Display author if available
            const authorElement = document.getElementById('recipe-author');
            if (author && author.trim()) {
                authorElement.textContent = ' | By ' + author;
                authorElement.style.display = 'inline';
            } else {
                authorElement.textContent = '';
                authorElement.style.display = 'none';
            }
            
            this.elements.recipeSource.style.display = 'block';
        } catch (e) {
            this.elements.recipeSource.style.display = 'none';
        }
    },

    renderDescription(description) {
        const descriptionEl = document.getElementById('recipe-description');
        const descriptionText = document.getElementById('description-text');
        const readMoreBtn = document.getElementById('read-more-btn');
        
        if (!description || !description.trim()) {
            descriptionEl.style.display = 'none';
            return;
        }
        
        descriptionText.textContent = description;
        descriptionEl.style.display = 'block';
        
        // Show "Read More" button if description is longer than 200 characters
        if (description.length > 200) {
            readMoreBtn.style.display = 'inline-block';
            descriptionText.classList.add('truncated');
            
            // Toggle expanded state
            readMoreBtn.onclick = () => {
                const isTruncated = descriptionText.classList.contains('truncated');
                if (isTruncated) {
                    descriptionText.classList.remove('truncated');
                    readMoreBtn.textContent = 'Read Less';
                } else {
                    descriptionText.classList.add('truncated');
                    readMoreBtn.textContent = 'Read More';
                }
            };
        } else {
            readMoreBtn.style.display = 'none';
            descriptionText.classList.remove('truncated');
        }
    },

    renderDietaryBadges(dietaryInfo) {
        const badgesEl = document.getElementById('dietary-badges');
        
        if (!dietaryInfo || !Array.isArray(dietaryInfo) || dietaryInfo.length === 0) {
            badgesEl.style.display = 'none';
            return;
        }
        
        // Map dietary labels to emoji icons and CSS classes
        const dietaryMap = {
            'Vegan': { emoji: '🌱', class: 'badge-vegan' },
            'Vegetarian': { emoji: '🥗', class: 'badge-vegetarian' },
            'Gluten-Free': { emoji: '🚫', class: 'badge-gluten-free' },
            'Dairy-Free': { emoji: '🥛', class: 'badge-dairy-free' },
            'Keto': { emoji: '🥓', class: 'badge-keto' },
            'Paleo': { emoji: '🦴', class: 'badge-paleo' },
            'Low-Carb': { emoji: '📉', class: 'badge-low-carb' },
            'Low-Sodium': { emoji: '🧂', class: 'badge-low-sodium' },
            'Kosher': { emoji: '✡️', class: 'badge-kosher' },
            'Halal': { emoji: '☪️', class: 'badge-halal' }
        };
        
        const badges = dietaryInfo
            .map(label => {
                const info = dietaryMap[label];
                if (!info) return null;
                
                return `<span class="dietary-badge ${info.class}">
                    <span class="badge-emoji">${info.emoji}</span>
                    <span class="badge-text">${label}</span>
                </span>`;
            })
            .filter(badge => badge !== null);
        
        if (badges.length > 0) {
            badgesEl.innerHTML = badges.join('');
            badgesEl.style.display = 'flex';
        } else {
            badgesEl.style.display = 'none';
        }
    },

    renderVideoButton(videoData) {
        const videoBtn = document.getElementById('video-btn');
        
        if (!videoData || !videoData.url) {
            videoBtn.style.display = 'none';
            return;
        }
        
        // Show the button
        videoBtn.style.display = 'inline-flex';
        
        // Initialize video modal if not already done
        if (!window.videoModal) {
            window.videoModal = new VideoModal();
        }
        
        // Remove existing click handler and add new one
        const newBtn = videoBtn.cloneNode(true);
        videoBtn.parentNode.replaceChild(newBtn, videoBtn);
        
        newBtn.addEventListener('click', () => {
            window.videoModal.open(videoData);
        });
    },

    renderDifficulty(difficulty) {
        const difficultyEl = document.getElementById('recipe-difficulty');
        
        if (!difficulty) {
            difficultyEl.style.display = 'none';
            return;
        }
        
        // Map difficulty levels to emoji and color class
        const difficultyMap = {
            'Easy': { emoji: '📊', class: 'difficulty-easy' },
            'Medium': { emoji: '⚡', class: 'difficulty-medium' },
            'Hard': { emoji: '🔥', class: 'difficulty-hard' }
        };
        
        const info = difficultyMap[difficulty];
        if (!info) {
            difficultyEl.style.display = 'none';
            return;
        }
        
        difficultyEl.innerHTML = `<span class="difficulty-badge ${info.class}">
            <span class="difficulty-emoji">${info.emoji}</span>
            <span class="difficulty-text">${difficulty}</span>
        </span>`;
        difficultyEl.style.display = 'flex';
    },

    renderTaxonomy(metadata) {
        const taxonomyEl = document.getElementById('recipe-taxonomy');
        
        if (!metadata) {
            taxonomyEl.style.display = 'none';
            return;
        }
        
        const items = [];
        
        // Add category
        if (metadata.category && Array.isArray(metadata.category) && metadata.category.length > 0) {
            const categories = metadata.category.slice(0, 3);
            const categoryText = categories.join(', ') + (metadata.category.length > 3 ? '...' : '');
            items.push(`<span class="taxonomy-item"><span class="taxonomy-label">Category:</span> ${categoryText}</span>`);
        }
        
        // Add cuisine
        if (metadata.cuisine && Array.isArray(metadata.cuisine) && metadata.cuisine.length > 0) {
            const cuisines = metadata.cuisine.slice(0, 3);
            const cuisineText = cuisines.join(', ') + (metadata.cuisine.length > 3 ? '...' : '');
            items.push(`<span class="taxonomy-item"><span class="taxonomy-label">Cuisine:</span> ${cuisineText}</span>`);
        }
        
        // Add keywords (show first 5)
        if (metadata.keywords && Array.isArray(metadata.keywords) && metadata.keywords.length > 0) {
            const keywords = metadata.keywords.slice(0, 5);
            const keywordText = keywords.join(', ') + (metadata.keywords.length > 5 ? '...' : '');
            items.push(`<span class="taxonomy-item"><span class="taxonomy-label">Tags:</span> ${keywordText}</span>`);
        }
        
        if (items.length > 0) {
            taxonomyEl.innerHTML = items.join('');
            taxonomyEl.style.display = 'flex';
        } else {
            taxonomyEl.style.display = 'none';
        }
    },

    renderRating(rating) {
        const ratingEl = document.getElementById('recipe-rating');
        
        if (!rating || !rating.value) {
            ratingEl.style.display = 'none';
            return;
        }
        
        const ratingValue = parseFloat(rating.value);
        const ratingCount = parseInt(rating.count);
        
        if (isNaN(ratingValue) || ratingValue <= 0) {
            ratingEl.style.display = 'none';
            return;
        }
        
        // Create star visualization
        const fullStars = Math.floor(ratingValue);
        const hasHalfStar = (ratingValue % 1) >= 0.5;
        const emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);
        
        const starsHTML = 
            '★'.repeat(fullStars) + 
            (hasHalfStar ? '⯨' : '') + 
            '☆'.repeat(emptyStars);
        
        const countText = ratingCount && !isNaN(ratingCount) && ratingCount > 0 
            ? ` <span class="rating-count">(${ratingCount.toLocaleString()} ${ratingCount === 1 ? 'review' : 'reviews'})</span>` 
            : '';
        
        ratingEl.innerHTML = `
            <span class="stars">${starsHTML}</span>
            <span class="rating-value">${ratingValue.toFixed(1)}/5</span>
            ${countText}
        `;
        ratingEl.style.display = 'flex';
    },

    copyIngredientsToClipboard() {
        const ingredients = [];
        this.elements.ingredientsList.querySelectorAll('.ingredient-text').forEach(span => {
            ingredients.push(span.textContent);
        });

        const text = ingredients.join('\n');
        navigator.clipboard.writeText(text).then(() => {
            this.showToast('Ingredients copied to clipboard!');
        }).catch(() => {
            this.showToast('Failed to copy ingredients', 3000);
        });
    },

    copyInstructionsToClipboard() {
        const instructions = [];
        this.elements.instructionsList.querySelectorAll('li').forEach((li, index) => {
            const text = li.textContent.trim();
            instructions.push(`${index + 1}. ${text}`);
        });

        const text = instructions.join('\n');
        navigator.clipboard.writeText(text).then(() => {
            this.showToast('Instructions copied to clipboard!');
        }).catch(() => {
            this.showToast('Failed to copy instructions', 3000);
        });
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    startTitleEdit() {
        const titleEl = this.elements.recipeTitle;
        
        // Make title editable
        titleEl.contentEditable = 'true';
        titleEl.classList.add('editing');
        titleEl.focus();
        
        // Select all text
        const range = document.createRange();
        range.selectNodeContents(titleEl);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        
        // Save on Enter or blur
        const saveTitle = () => {
            this.endTitleEdit();
        };
        
        const handleKeyDown = (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveTitle();
            } else if (e.key === 'Escape') {
                // Restore original title
                titleEl.textContent = this.getSavedTitle(this.currentRecipeUrl) || this.originalRecipeTitle;
                this.endTitleEdit();
            }
        };
        
        titleEl.addEventListener('blur', saveTitle, { once: true });
        titleEl.addEventListener('keydown', handleKeyDown);
        
        // Store handler reference for cleanup
        titleEl._keydownHandler = handleKeyDown;
    },

    endTitleEdit() {
        const titleEl = this.elements.recipeTitle;
        
        // Save the new title
        const newTitle = titleEl.textContent.trim();
        if (newTitle && newTitle !== this.originalRecipeTitle) {
            this.saveTitlePreference(this.currentRecipeUrl, newTitle);
            this.showToast('Title updated!', 2000);
        }
        
        // Clean up
        titleEl.contentEditable = 'false';
        titleEl.classList.remove('editing');
        
        if (titleEl._keydownHandler) {
            titleEl.removeEventListener('keydown', titleEl._keydownHandler);
            delete titleEl._keydownHandler;
        }
    },

    saveTitlePreference(recipeUrl, title) {
        if (!recipeUrl) return;
        
        try {
            const key = `cleanplate_title_${this.getUrlHash(recipeUrl)}`;
            const preference = {
                title: title,
                timestamp: Date.now()
            };
            localStorage.setItem(key, JSON.stringify(preference));
        } catch (e) {
            console.warn('Failed to save title preference:', e);
        }
    },

    getSavedTitle(recipeUrl) {
        if (!recipeUrl) return null;
        
        try {
            const key = `cleanplate_title_${this.getUrlHash(recipeUrl)}`;
            const saved = localStorage.getItem(key);
            
            if (saved) {
                const preference = JSON.parse(saved);
                
                // Check if preference is less than 30 days old
                const age = Date.now() - preference.timestamp;
                const maxAge = 30 * 24 * 60 * 60 * 1000;
                
                if (age < maxAge) {
                    return preference.title;
                }
            }
        } catch (e) {
            console.warn('Failed to load title preference:', e);
        }
        
        return null;
    },

    getUrlHash(url) {
        // Simple hash function for URL
        let hash = 0;
        for (let i = 0; i < url.length; i++) {
            const char = url.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return Math.abs(hash).toString(36);
    }
};

// Featured Recipes Carousel
class FeaturedCarousel {
    /**
     * @param {number} visibleCount  Cards visible at once (default 5)
     * @param {number} intervalMs    Auto-advance delay in ms (default 3000)
     */
    constructor(visibleCount = 5, intervalMs = 3000) {
        this.visibleCount = visibleCount;
        this.intervalMs   = intervalMs;
        this.current      = 0;    // index of first visible card
        this.total        = 0;    // real (non-clone) card count
        this.cardWidth    = 0;    // px, set on render
        this.timer        = null;
        this.paused       = false;
        this.section      = document.getElementById('featured-carousel');
        this.track        = document.getElementById('featured-track');
    }

    /** Fetch /data/featured.json and render. Silently does nothing if missing. */
    async load() {
        if (!this.section || !this.track) return;
        try {
            const res = await fetch('./data/featured.json');
            if (!res.ok) return;
            const data = await res.json();
            const pool = (data.recipes || []).filter(r => r.url && r.title);
            if (pool.length === 0) return;
            // Shuffle the full pool client-side, then slice to list_size
            const listSize = data.list_size || this.visibleCount;
            for (let i = pool.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [pool[i], pool[j]] = [pool[j], pool[i]];
            }
            this.render(pool.slice(0, listSize));
        } catch (_) {
            // JSON not published yet — section stays hidden
        }
    }

    render(recipes) {
        this.total = recipes.length;
        const needsLoop = recipes.length > this.visibleCount;

        // Clone first `visibleCount` cards so the loop looks seamless
        const all = needsLoop
            ? [...recipes, ...recipes.slice(0, this.visibleCount)]
            : recipes;

        this.track.innerHTML = all.map(r => this.buildCard(r)).join('');

        // Show section BEFORE measuring clientWidth — hidden elements return 0
        this.section.style.display = 'block';
        this.sizeCards();

        // Bind clicks on every card (including clones)
        this.track.querySelectorAll('.featured-card').forEach(card => {
            card.addEventListener('click', () => this.handleCardClick(card));
        });

        if (needsLoop) {
            this.setOffset(false);
            this.bindHover();
            this.startTimer();
        }

        // Touch swipe always active when there are multiple cards
        if (this.total > 1) {
            this.bindTouch();
        }

        // Recompute card widths on window resize
        window.addEventListener('resize', () => {
            this.sizeCards();
            this.setOffset(false);
        });
    }

    buildCard(recipe) {
        const esc = s => String(s || '')
            .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
            .replace(/</g, '&lt;').replace(/>/g, '&gt;');

        const displayDomain = (recipe.domain || '').replace(/^www\./, '');
        const imgHtml = recipe.image
            ? `<div class="fcard-image"><img src="${esc(recipe.image)}" alt="" loading="lazy" onerror="this.parentElement.style.display='none'"></div>`
            : '';

        return `<div class="featured-card" data-url="${esc(recipe.url)}">
                    <div class="featured-card-inner">
                        ${imgHtml}
                        <div class="fcard-title">${esc(recipe.title)}</div>
                        <div class="fcard-domain">${esc(displayDomain)}</div>
                    </div>
                </div>`;
    }

    sizeCards() {
        const vpWidth = this.section.clientWidth;
        // Responsive effective count: fewer cards on smaller screens
        const w = window.innerWidth;
        let effectiveCount;
        if (w < 480) {
            effectiveCount = 1.2; // 1 full card + peek at the next
        } else if (w < 768) {
            effectiveCount = 2.4; // 2 full cards + peek
        } else {
            effectiveCount = Math.min(this.visibleCount, this.total);
        }
        this.effectiveCount = effectiveCount;
        this.cardWidth = vpWidth / effectiveCount;

        const totalCards = this.track.children.length;
        Array.from(this.track.children).forEach(c => {
            c.style.width = this.cardWidth + 'px';
        });
        this.track.style.width = (totalCards * this.cardWidth) + 'px';
    }

    setOffset(animate = true) {
        this.track.style.transition = animate
            ? 'transform 0.45s cubic-bezier(0.25, 0.46, 0.45, 0.94)'
            : 'none';
        this.track.style.transform = `translateX(${-this.current * this.cardWidth}px)`;
    }

    advance() {
        this.current++;
        this.setOffset(true);

        // After slide, if we've entered the clone zone, snap back instantly
        const onEnd = () => {
            if (this.current >= this.total) {
                this.current = 0;
                this.setOffset(false);
            }
        };
        this.track.addEventListener('transitionend', onEnd, { once: true });
    }

    retreat() {
        if (this.current > 0) {
            this.current--;
            this.setOffset(true);
        } else {
            // At position 0 — snap to last real card without animation
            this.current = this.total - 1;
            this.setOffset(false);
        }
    }

    startTimer() {
        // paused check lives here so touch/manual calls always navigate
        this.timer = setInterval(() => {
            if (!this.paused) this.advance();
        }, this.intervalMs);
    }

    bindHover() {
        const vp = this.track.parentElement;
        vp.addEventListener('mouseenter', () => { this.paused = true; });
        vp.addEventListener('mouseleave', () => { this.paused = false; });
    }

    bindTouch() {
        const vp = this.track.parentElement;
        let startX = 0;

        vp.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            this.paused = true;
        }, { passive: true });

        vp.addEventListener('touchend', (e) => {
            const deltaX = e.changedTouches[0].clientX - startX;
            this.paused = false;
            // Require at least 40px movement to count as a deliberate swipe
            if (Math.abs(deltaX) > 40) {
                deltaX > 0 ? this.retreat() : this.advance();
            }
        }, { passive: true });
    }

    handleCardClick(card) {
        const url = card.dataset.url;
        if (!url) return;

        const input = document.getElementById('url-input');
        if (!input) return;

        input.value = url;
        input.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Trigger the form submit so the recipe loads immediately
        const form = document.getElementById('parse-form');
        if (form) {
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        }
    }
}

// Main Application
class CleanPlateApp {
    constructor() {
        this.api = new CleanPlateAPI();
        this.init();
    }

    init() {
        // Bind form submit handler
        UI.elements.form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.handleFormSubmit();
        });

        // Bind back button
        UI.elements.backButton.addEventListener('click', () => {
            UI.hideRecipe();
        });

        // Bind copy buttons
        if (UI.elements.copyIngredientsBtn) {
            UI.elements.copyIngredientsBtn.addEventListener('click', () => {
                UI.copyIngredientsToClipboard();
            });
        }

        if (UI.elements.copyInstructionsBtn) {
            UI.elements.copyInstructionsBtn.addEventListener('click', () => {
                UI.copyInstructionsToClipboard();
            });
        }

        // Bind print button
        UI.elements.printBtn.addEventListener('click', () => {
            window.print();
        });

        // Bind image selector button
        UI.elements.imageSelectorBtn.addEventListener('click', () => {
            if (UI.currentCarousel) {
                UI.currentCarousel.show();
            }
        });

        // Bind image hidden change button
        UI.elements.imageHiddenChange.addEventListener('click', () => {
            if (UI.currentCarousel) {
                UI.currentCarousel.show();
            }
        });

        // Bind confidence toggle button
        UI.elements.confidenceToggle.addEventListener('click', () => {
            UI.toggleConfidenceDetails();
        });

        // Bind title edit button
        UI.elements.titleEditBtn.addEventListener('click', () => {
            UI.startTitleEdit();
        });

        // Bind view selector dropdown
        if (UI.elements.viewSelectorBtn) {
            UI.elements.viewSelectorBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                UI.toggleViewDropdown();
            });
        }

        // Bind view dropdown items
        if (UI.elements.viewDropdownMenu) {
            const dropdownItems = UI.elements.viewDropdownMenu.querySelectorAll('.view-dropdown-item');
            dropdownItems.forEach(item => {
                item.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const viewType = item.dataset.view;
                    UI.setView(viewType);
                });
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (UI.elements.viewDropdownMenu && 
                UI.elements.viewDropdownMenu.style.display !== 'none' &&
                !UI.elements.viewDropdownMenu.contains(e.target) &&
                !UI.elements.viewSelectorBtn.contains(e.target)) {
                UI.closeViewDropdown();
            }
        });

        // Initialize view from saved preference
        UI.initializeView();
    }

    async handleFormSubmit() {
        const url = UI.elements.urlInput.value.trim();

        if (!url) {
            UI.showError('Please enter a recipe URL.');
            return;
        }

        // Reset state and UI
        RecipeState.reset();
        UI.hideError();
        UI.showLoading();

        try {
            // Parse the recipe
            const result = await this.api.parseRecipe(url);

            // Update state
            RecipeState.setRecipe(result.data);

            // Display recipe
            UI.showRecipe(result);

        } catch (error) {
            RecipeState.setError(error);

            if (error instanceof RecipeError) {
                UI.showError(error.message, error.suggestions);
            } else {
                UI.showError('An unexpected error occurred. Please try again.');
            }

            console.error('Recipe extraction error:', error);

        } finally {
            UI.hideLoading();
        }
    }
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new CleanPlateApp();

    // Load featured carousel (5 visible, 3 s advance; silently hidden if no JSON yet)
    const carousel = new FeaturedCarousel(5, 3000);
    carousel.load();
});
