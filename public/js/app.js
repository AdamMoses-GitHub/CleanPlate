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

// API Client
class CleanPlateAPI {
    constructor(endpoint = '../parser.php') {
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
        errorContainer: document.getElementById('error-message'),
        recipeDisplay: document.getElementById('recipe-display'),
        recipeTitle: document.getElementById('recipe-title'),
        recipeUrl: document.getElementById('recipe-url'),
        recipeMetadata: document.getElementById('recipe-metadata'),
        ingredientsList: document.getElementById('ingredients-list'),
        instructionsList: document.getElementById('instructions-list'),
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
        const { data, phase } = recipeData;

        // Show fallback toast if Phase 2 was used
        if (phase === 2) {
            this.showToast('Using deep-scan mode for this site.');
        }

        // Set title and source
        this.elements.recipeTitle.textContent = data.title || 'Untitled Recipe';
        this.elements.recipeUrl.textContent = data.source?.siteName || data.source?.url || 'Unknown Source';
        this.elements.recipeUrl.href = data.source?.url || '#';

        // Set metadata if available
        if (data.metadata) {
            this.renderMetadata(data.metadata);
        } else {
            this.elements.recipeMetadata.innerHTML = '';
        }

        // Render ingredients
        this.renderIngredients(data.ingredients || []);

        // Render instructions
        this.renderInstructions(data.instructions || []);

        // Show recipe display
        this.elements.recipeDisplay.style.display = 'block';
        this.elements.errorContainer.style.display = 'none';

        // Scroll to recipe
        this.elements.recipeDisplay.scrollIntoView({ behavior: 'smooth', block: 'start' });
    },

    renderMetadata(metadata) {
        const items = [];
        
        if (metadata.prepTime) {
            items.push({ label: 'Prep Time', value: metadata.prepTime });
        }
        if (metadata.cookTime) {
            items.push({ label: 'Cook Time', value: metadata.cookTime });
        }
        if (metadata.servings) {
            items.push({ label: 'Servings', value: metadata.servings });
        }

        if (items.length === 0) {
            this.elements.recipeMetadata.style.display = 'none';
            return;
        }

        this.elements.recipeMetadata.style.display = 'flex';
        this.elements.recipeMetadata.innerHTML = items.map(item => `
            <div class="metadata-item">
                <span class="metadata-label">${this.escapeHtml(item.label)}</span>
                <span class="metadata-value">${this.escapeHtml(item.value)}</span>
            </div>
        `).join('');
    },

    renderIngredients(ingredients) {
        if (!Array.isArray(ingredients) || ingredients.length === 0) {
            this.elements.ingredientsList.innerHTML = '<li>No ingredients found</li>';
            return;
        }

        this.elements.ingredientsList.innerHTML = ingredients.map(ingredient => {
            // Handle both string and object formats
            const text = typeof ingredient === 'string' 
                ? ingredient 
                : `${ingredient.quantity || ''} ${ingredient.unit || ''} ${ingredient.item || ''}`.trim();
            
            return `<li>${this.escapeHtml(text)}</li>`;
        }).join('');
    },

    renderInstructions(instructions) {
        if (!Array.isArray(instructions) || instructions.length === 0) {
            this.elements.instructionsList.innerHTML = '<li>No instructions found</li>';
            return;
        }

        this.elements.instructionsList.innerHTML = instructions.map(instruction => {
            const text = typeof instruction === 'string' 
                ? instruction 
                : instruction.text || '';
            
            return `<li>${this.escapeHtml(text)}</li>`;
        }).join('');
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
