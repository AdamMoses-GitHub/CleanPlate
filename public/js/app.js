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

// API Client
class CleanPlateAPI {
    constructor(endpoint = '../api/parser.php') {
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
        recipeMetadata: document.getElementById('recipe-metadata'),
        ingredientsList: document.getElementById('ingredients-list'),
        instructionsList: document.getElementById('instructions-list'),
        copyIngredientsBtn: document.getElementById('copy-ingredients-btn'),
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
        toast: document.getElementById('toast-notification')
    },

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

    showRecipe(recipeData) {
        const { data, phase, confidence, confidenceLevel, confidenceDetails } = recipeData;

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
        this.elements.recipeTitle.textContent = data.title || 'Untitled Recipe';

        // Set metadata if available
        if (data.metadata) {
            this.renderMetadata(data.metadata);
        } else {
            this.elements.recipeMetadata.innerHTML = '';
            this.elements.recipeMetadata.style.display = 'none';
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
            this.elements.ingredientsList.innerHTML = '<li><button class="ingredient-checkbox" disabled></button><span class="ingredient-text">No ingredients found</span></li>';
            return;
        }

        this.elements.ingredientsList.innerHTML = ingredients.map((ingredient, index) => {
            // Handle both string and object formats
            const text = typeof ingredient === 'string' 
                ? ingredient 
                : `${ingredient.quantity || ''} ${ingredient.unit || ''} ${ingredient.item || ''}`.trim();
            
            return `
                <li>
                    <button class="ingredient-checkbox" data-index="${index}"></button>
                    <span class="ingredient-text">${this.escapeHtml(text)}</span>
                </li>
            `;
        }).join('');

        // Add checkbox click handlers
        this.elements.ingredientsList.querySelectorAll('.ingredient-checkbox').forEach(checkbox => {
            checkbox.addEventListener('click', (e) => {
                e.preventDefault();
                checkbox.classList.toggle('checked');
                const textSpan = checkbox.nextElementSibling;
                textSpan.classList.toggle('checked');
            });
        });
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

    copyIngredientsToClipboard() {
        const ingredients = [];
        this.elements.ingredientsList.querySelectorAll('.ingredient-text').forEach(span => {
            if (!span.classList.contains('checked')) {
                ingredients.push(span.textContent);
            }
        });

        const text = ingredients.join('\n');
        navigator.clipboard.writeText(text).then(() => {
            this.showToast('Ingredients copied to clipboard!');
        }).catch(() => {
            this.showToast('Failed to copy ingredients', 3000);
        });
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

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

        // Bind copy ingredients button
        UI.elements.copyIngredientsBtn.addEventListener('click', () => {
            UI.copyIngredientsToClipboard();
        });

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
});
