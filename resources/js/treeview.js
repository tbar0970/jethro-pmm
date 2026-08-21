// treeview.js

/**
 * Quercus.js: A Lightweight and Customizable JavaScript Treeview Library
 *
 * Provides hierarchical data display with search, multi-node selection,
 * and smooth expand/collapse animations. Expand/collapse is triggered
 * by clicking on the dedicated icon (+/-), while selection/deselection
 * happens by clicking on the node's text (multi-select enabled by config).
 *
 * Optional "Select All/Deselect All" and "Expand All/Collapse All" buttons.
 * Custom Node Rendering via onRenderNode callback.
 * Option to disable node selection.
 * Option to cascade selection to children when a parent node is selected.
 * - If `multiSelectEnabled` is false, it's a single-select cascade (selects node and children, clears others).
 * - If `multiSelectEnabled` is true, it's a multi-select cascade (adds node and children to selection).
 * Option to display checkboxes for node selection, positioned between expander and label.
 * Support for initial selection via data and programmatic selection via selectNodeById.
 * Individual node selectable property to control if a node can be selected.
 * Option to define the key for the node's display name (e.g., 'label', 'appellation' instead of 'name').
 */
(function () { // Anonymous IIFE for encapsulation

    /**
     * Helper function to get the node's display name for internal use (e.g., search or selection callback).
     * This now relies on the original nodeData and the configured nodeNameKey for consistency regardless of custom rendering.
     * @param {HTMLElement} liElement The <li> DOM element representing the node.
     * @param {string} nodeNameKey The key to use for retrieving the node's name from its data.
     * @returns {string} The node's name from its original data.
     */
    function getDisplayNameFromNodeElement(liElement, nodeNameKey) {
        try {
            const nodeData = JSON.parse(liElement.dataset.nodeData);
            return nodeData[nodeNameKey] || nodeData.name || ''; // Use nodeNameKey, fallback to 'name', then empty string
        } catch (e) {
            console.error("Quercus.js: Error parsing node data for display name:", e);
            return 'Unnamed Node';
        }
    }

    // Define the Treeview class
    class Treeview {
        constructor(options) {
            this.options = {
                containerId: null,
                data: [],
                searchEnabled: false,
                searchPlaceholder: 'Search tree...',
                showChildrenOnSearch: false,
                initiallyExpanded: false,
                multiSelectEnabled: false,
                onSelectionChange: null,
                onRenderNode: null,
                showSelectAllButton: false,
                showInvertSelectionButton: false,
                showExpandCollapseAllButtons: false,
                nodeSelectionEnabled: true,
                cascadeSelectChildren: false,
                checkboxSelectionEnabled: false,
                nodeNameKey: 'name'
            };
            Object.assign(this.options, options);

            this.treeviewContainer = null;
            this.treeSearchInput = null;
            this.selectedNodes = new Set();
            this.selectAllButton = null;
            this.invertSelectionButton = null;
            this.expandAllButton = null;
            this.collapseAllButton = null;
            this._initialExpansionStates = new Map(); // Store initial expansion states for search reset


            this._initialize();
        }

        _initialize() {
            if (!this.options.containerId) {
                console.error("Quercus.js: containerId is required.");
                return;
            }

            this.treeviewContainer = document.getElementById(this.options.containerId);
            if (!this.treeviewContainer) {
                console.error(`Quercus.js: Element with ID '${this.options.containerId}' not found.`);
                return;
            }

            this.treeviewContainer.classList.add('custom-treeview-wrapper');

            this._createControls();
            this._renderTree(this.options.data, this.treeviewContainer);

            // Apply initial expansion state after rendering
            if (this.options.initiallyExpanded) {
                this.treeviewContainer.querySelectorAll('li.has-children').forEach(li => {
                    li.classList.add('expanded');
                    const expander = li.querySelector('.treeview-expander');
                    if (expander) expander.textContent = '-';
                    const childUl = li.querySelector('ul');
                    if (childUl) {
                        childUl.style.height = 'auto'; // Ensure it's fully expanded
                    }
                });
            } else {
                this.treeviewContainer.querySelectorAll('li.has-children').forEach(li => {
                    li.classList.remove('expanded');
                    const expander = li.querySelector('.treeview-expander');
                    if (expander) expander.textContent = '+';
                    const childUl = li.querySelector('ul');
                    if (childUl) {
                        childUl.style.height = ''; // Reset height to allow CSS to manage (collapse)
                    }
                });
            }


            // Handle initial selection from data after rendering the tree
            if (this.options.nodeSelectionEnabled) {
                const initiallySelectedNodeIds = [];
                // Recursively find all initially selected nodes
                const findInitiallySelected = (nodes) => {
                    nodes.forEach(node => {
                        // Only consider nodes that are explicitly selectable or default to selectable
                        if (node.selected && (node.selectable === undefined || node.selectable === true)) {
                            initiallySelectedNodeIds.push(node.id);
                        }
                        if (node.children) {
                            findInitiallySelected(node.children);
                        }
                    });
                };
                findInitiallySelected(this.options.data);

                // Process initial selections. _selectNode handles multi/single/cascade logic.
                // If multiSelectEnabled is false, only the last one processed will remain selected.
                initiallySelectedNodeIds.forEach(id => {
                    const nodeElement = this.treeviewContainer.querySelector(`[data-id="${id}"]`);
                    if (nodeElement) {
                        this._selectNode(nodeElement, true); // Force select
                    }
                });
            }
        }

        /**
         * Helper function to recursively get all descendant <li> elements of a given node.
         * @param {HTMLElement} liElement The <li> DOM element representing the parent node.
         * @returns {Array<HTMLElement>} An array of all descendant <li> elements.
         */
        _getAllDescendants(liElement) {
            const descendants = [];
            const queue = [liElement]; // Start with the parent node itself in the queue

            let head = 0;
            while (head < queue.length) {
                const currentLi = queue[head++]; // Dequeue current node
                const childUl = currentLi.querySelector('ul');
                if (childUl) {
                    // Iterate directly over children of the UL to avoid adding parent itself repeatedly
                    Array.from(childUl.children).forEach(childLi => {
                        descendants.push(childLi);
                        queue.push(childLi); // Enqueue children for their descendants
                    });
                }
            }
            return descendants;
        }

        // Method to create all control elements (search, buttons)
        _createControls() {
            const controlsContainer = document.createElement('div');
            controlsContainer.className = 'treeview-controls-container';

            if (this.options.searchEnabled) {
                // Create a wrapper for the search input and clear button
                const searchInputWrapper = document.createElement('div');
                searchInputWrapper.classList.add('treeview-search-input-wrapper');
                controlsContainer.appendChild(searchInputWrapper);

                this.treeSearchInput = document.createElement('input');
                this.treeSearchInput.type = 'text';
                this.treeSearchInput.id = `treeSearch-${this.options.containerId}`;
                this.treeSearchInput.placeholder = this.options.searchPlaceholder; // Use the new option here
                this.treeSearchInput.classList.add('treeview-search-input');
                searchInputWrapper.appendChild(this.treeSearchInput);

                // Create the clear button
                const clearButton = document.createElement('span');
                clearButton.classList.add('treeview-search-clear');
                clearButton.textContent = '✕'; // Unicode 'X' character
                searchInputWrapper.appendChild(clearButton); // Append to wrapper, not input directly

                // Event listener for the search input
                this.treeSearchInput.addEventListener('input', (event) => {
                    this._searchTree(event.target.value);
                    // Show/hide clear button based on input value
                    if (event.target.value.length > 0) {
                        clearButton.style.display = 'block';
                    } else {
                        clearButton.style.display = 'none';
                    }
                });

                // Event listener for the clear button
                clearButton.addEventListener('click', () => {
                    this.treeSearchInput.value = ''; // Clear the input field
                    this._searchTree(''); // Trigger search with empty string
                    clearButton.style.display = 'none'; // Hide the clear button
                });

                // Initially hide the clear button if the input is empty
                if (this.treeSearchInput.value.length === 0) {
                    clearButton.style.display = 'none';
                }
            }

            const buttonContainer = document.createElement('div');
            buttonContainer.classList.add('treeview-button-container');

            // Select All button now shows if multi-select and node selection are enabled,
            // AND cascadeSelectChildren is NOT enabled (as its behavior would conflict with "select all")
            if (this.options.showSelectAllButton && this.options.multiSelectEnabled && this.options.nodeSelectionEnabled && !this.options.cascadeSelectChildren) {
                this.selectAllButton = document.createElement('button');
                this.selectAllButton.classList.add('treeview-control-button', 'treeview-select-all');
                this.selectAllButton.textContent = 'Select All';
                buttonContainer.appendChild(this.selectAllButton);

                this.selectAllButton.addEventListener('click', () => this._toggleSelectAll());
            }

            if (this.options.showInvertSelectionButton && this.options.multiSelectEnabled && this.options.nodeSelectionEnabled && !this.options.cascadeSelectChildren) {
                this.invertSelectionButton = document.createElement('button');
                this.invertSelectionButton.classList.add('treeview-control-button', 'treeview-invert-selection');
                this.invertSelectionButton.textContent = 'Invert Selection';
                buttonContainer.appendChild(this.invertSelectionButton);

                this.invertSelectionButton.addEventListener('click', () => this.invertSelection());
            }

            if (this.options.showExpandCollapseAllButtons) {
                this.expandAllButton = document.createElement('button');
                this.expandAllButton.type = 'button';
                this.expandAllButton.classList.add('treeview-control-button', 'treeview-expand-all', 'btn', 'btn-mini');
                this.expandAllButton.textContent = 'Expand All';
                buttonContainer.appendChild(this.expandAllButton);

                this.expandAllButton.addEventListener('click', () => this._expandAll());

                this.collapseAllButton = document.createElement('button');
                this.collapseAllButton.type = 'button';
                this.collapseAllButton.classList.add('treeview-control-button', 'treeview-collapse-all', 'btn', 'btn-mini');
                this.collapseAllButton.textContent = 'Collapse All';
                buttonContainer.appendChild(this.collapseAllButton);

                this.collapseAllButton.addEventListener('click', () => this._collapseAll());
            }


            if (buttonContainer.children.length > 0) {
                controlsContainer.appendChild(buttonContainer);
            }
            if (controlsContainer.children.length > 0) {
                this.treeviewContainer.appendChild(controlsContainer);
            }
        }

        // Toggle Select All / Deselect All logic
        _toggleSelectAll() {
            if (!this.options.nodeSelectionEnabled) {
                console.warn("Quercus.js: Node selection is disabled, cannot select/deselect all nodes.");
                return;
            }
            if (!this.options.multiSelectEnabled) {
                console.warn("Quercus.js: Select All/Deselect All requires multi-select to be enabled.");
                return;
            }
            // Defensive check for cascading options: This button should not be active if cascading selection is on.
            if (this.options.cascadeSelectChildren) {
                console.warn("Quercus.js: Select All/Deselect All is not applicable when cascading selection is enabled.");
                return;
            }

            // Filter for only selectable nodes
            const allSelectableNodes = Array.from(this.treeviewContainer.querySelectorAll('li')).filter(li => {
                try {
                    const nodeData = JSON.parse(li.dataset.nodeData);
                    return nodeData.selectable === undefined || nodeData.selectable === true;
                } catch (e) {
                    console.error("Quercus.js: Error parsing node data for selectable check:", e);
                    return true; // Default to selectable if data is malformed
                }
            });

            const currentlySelectedCount = this.selectedNodes.size;
            const shouldSelectAll = (currentlySelectedCount === 0 || currentlySelectedCount < allSelectableNodes.length);

            allSelectableNodes.forEach(li => {
                const checkbox = li.querySelector('.treeview-checkbox'); // Get checkbox if it exists
                if (shouldSelectAll) { // If we should select all
                    if (!this.selectedNodes.has(li)) { // Only add if not already in set
                        this.selectedNodes.add(li);
                        li.classList.add('selected');
                        if (checkbox) checkbox.checked = true; // Check checkbox if present
                    }
                } else { // If we should deselect all
                    if (this.selectedNodes.has(li)) { // Only remove if currently in set
                        this.selectedNodes.delete(li);
                        li.classList.remove('selected');
                        if (checkbox) checkbox.checked = false; // Uncheck checkbox if present
                    }
                }
            });

            if (this.selectAllButton) {
                this.selectAllButton.textContent = shouldSelectAll ? 'Deselect All' : 'Select All';
            }
            this._triggerSelectionChange();
        }

        // Expand All logic
        _expandAll() {
            const allExpandableNodes = this.treeviewContainer.querySelectorAll('li.has-children');
            allExpandableNodes.forEach(li => {
                if (!li.classList.contains('expanded')) {
                    li.classList.add('expanded');
                    const expander = li.querySelector('.treeview-expander');
                    if (expander) expander.textContent = '-';
                    const childUl = li.querySelector('ul');
                    if (childUl) {
                        // Set height to 0 to prepare for transition
                        childUl.style.height = '0px';
                        // Use requestAnimationFrame to ensure reflow before setting final height
                        requestAnimationFrame(() => {
                            childUl.style.height = `${childUl.scrollHeight}px`;
                        });
                        // After transition, reset height to auto to allow natural content flow
                        childUl.addEventListener('transitionend', function handler() {
                            childUl.removeEventListener('transitionend', handler);
                            childUl.style.height = 'auto';
                        }, {once: true});
                    }
                }
            });
        }

        // Collapse All logic
        _collapseAll() {
            const allExpandableNodes = this.treeviewContainer.querySelectorAll('li.has-children');
            // Iterate in reverse to avoid layout issues during collapse animations
            for (let i = allExpandableNodes.length - 1; i >= 0; i--) {
                const li = allExpandableNodes[i];
                if (li.classList.contains('expanded')) {
                    li.classList.remove('expanded');
                    const expander = li.querySelector('.treeview-expander');
                    if (expander) expander.textContent = '+';
                    const childUl = li.querySelector('ul');
                    if (childUl) {
                        childUl.style.height = `${childUl.scrollHeight}px`; // Lock height for animation
                        requestAnimationFrame(() => {
                            childUl.style.height = '0px';
                        });
                        childUl.addEventListener('transitionend', function handler() {
                            childUl.removeEventListener('transitionend', handler);
                            childUl.style.height = ''; // Clear height after transition
                        }, {once: true});
                    }
                }
            }
        }


        // Function to render the tree from JSON data
        _renderTree(data, parentElement) {
            const ul = document.createElement('ul');
            parentElement.appendChild(ul);

            data.forEach(node => {
                const li = document.createElement('li');
                li.dataset.id = node.id;
                li.dataset.nodeData = JSON.stringify(node);

                const nodeContentWrapper = document.createElement('div');
                nodeContentWrapper.classList.add('treeview-node-content');

                // Check if node is selectable
                const isNodeSelectable = (node.selectable === undefined || node.selectable === true);
                if (!isNodeSelectable) {
                    nodeContentWrapper.classList.add('not-selectable');
                }

                // 1. Create and add the node's main content (text or custom rendering) first
                if (typeof this.options.onRenderNode === 'function') {
                    try {
                        this.options.onRenderNode(node, nodeContentWrapper);
                    } catch (e) {
                        console.error("Quercus.js: Error in custom node renderer:", e);
                        const nodeTextSpan = document.createElement('span'); // Fallback to default text
                        nodeTextSpan.classList.add('treeview-node-text');
                        nodeTextSpan.textContent = node[this.options.nodeNameKey] || node.name; // Use nodeNameKey
                        nodeContentWrapper.appendChild(nodeTextSpan);
                    }
                } else {
                    const nodeTextSpan = document.createElement('span');
                    nodeTextSpan.classList.add('treeview-node-text');
                    nodeTextSpan.textContent = node[this.options.nodeNameKey] || node.name; // Use nodeNameKey
                    nodeContentWrapper.appendChild(nodeTextSpan);
                }

                // 2. Create expander/placeholder and prepend it
                let expanderOrPlaceholder;
                if (node.children && node.children.length > 0) {
                    li.classList.add('has-children');
                    expanderOrPlaceholder = document.createElement('span');
                    expanderOrPlaceholder.classList.add('treeview-expander');
                    expanderOrPlaceholder.textContent = this.options.initiallyExpanded ? '-' : '+';
                } else {
                    expanderOrPlaceholder = document.createElement('span');
                    expanderOrPlaceholder.classList.add('treeview-expander-placeholder');
                }
                nodeContentWrapper.prepend(expanderOrPlaceholder); // Prepend so it's always first


                // 3. Add checkbox if enabled, right after the expander/placeholder
                if (this.options.checkboxSelectionEnabled) {
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.classList.add('treeview-checkbox');
                    checkbox.id = `checkbox-${node.id}`; // Give it an ID for potential <label for> later
                    checkbox.disabled = !isNodeSelectable; // Disable checkbox if node is not selectable

                    if (this.selectedNodes.has(li)) { // Ensure checkbox state matches initial selection from _initialize
                        checkbox.checked = true;
                    }

                    checkbox.addEventListener('change', (event) => {
                        // Pass the <li> element and the checkbox state to _selectNode
                        this._selectNode(li, event.target.checked);
                    });
                    // Insert checkbox right after the expander/placeholder
                    nodeContentWrapper.insertBefore(checkbox, expanderOrPlaceholder.nextSibling);
                }


                li.appendChild(nodeContentWrapper); // Append the wrapper to the list item

                // Recursively render children if they exist
                if (node.children && node.children.length > 0) {
                    // Initial expansion state for children is handled in _initialize, not here
                    this._renderTree(node.children, li);

                    expanderOrPlaceholder.addEventListener('click', (event) => {
                        const childUl = li.querySelector('ul');
                        if (childUl) {
                            if (li.classList.contains('expanded')) {
                                li.classList.remove('expanded');
                                expanderOrPlaceholder.textContent = '+';
                                childUl.style.height = `${childUl.scrollHeight}px`;
                                requestAnimationFrame(() => {
                                    childUl.style.height = '0px';
                                });
                                childUl.addEventListener('transitionend', function handler() {
                                    childUl.removeEventListener('transitionend', handler);
                                    childUl.style.height = '';
                                }, {once: true});
                            } else {
                                li.classList.add('expanded');
                                expanderOrPlaceholder.textContent = '-';
                                childUl.style.height = '0px';
                                requestAnimationFrame(() => {
                                    childUl.style.height = `${childUl.scrollHeight}px`;
                                });
                                childUl.addEventListener('transitionend', function handler() {
                                    childUl.removeEventListener('transitionend', handler);
                                    childUl.style.height = 'auto';
                                }, {once: true});
                            }
                        }
                        event.stopPropagation();
                    });
                }


                if (true) {
                    nodeContentWrapper.addEventListener('click', (event) => {
                        if (!event.target.classList.contains('treeview-expander') && !event.target.classList.contains('treeview-checkbox')) {
                            var nextUL = nodeContentWrapper.nextElementSibling; 
                            var isLeaf = !(nextUL && nextUL.tagName == 'UL');
                            if (this.options.nodeSelectionEnabled && isNodeSelectable &&  (isLeaf || !this.options.checkboxSelectionEnabled)) {
                                // treat the click as a selection
                                this._selectNode(li, !li.classList.contains('selected')); // Pass current selection state for toggle
                            } else if (!isLeaf) {
                                // treat the click as an expand/collapse
                                nodeContentWrapper.getElementsByClassName('treeview-expander')[0].click();
                            }
                        }
                        event.stopPropagation();
                    });
                } else if (!this.options.nodeSelectionEnabled || !isNodeSelectable) {
                    // Change cursor if selection is disabled (globally) or if this specific node is not selectable
                    nodeContentWrapper.style.cursor = 'default';
                }
                // If checkboxSelectionEnabled is true, cursor can remain 'pointer' as checkboxes are clickable.

                ul.appendChild(li);
            });
        }

        /**
         * Selects or deselects a node.
         * @param {HTMLElement} nodeElement The <li> DOM element of the node.
         * @param {boolean} [isSelected=true] Optional. The desired selection state. If not provided, it toggles (multi-select) or sets to true (single-select).
         * This parameter is particularly useful when called from a checkbox change event.
         */
        _selectNode(nodeElement, isSelected = null) {
            // Defensive check if selection is disabled (though event listener handles primary control)
            if (!this.options.nodeSelectionEnabled) {
                console.warn("Quercus.js: Attempted to select a node while selection is disabled.");
                return;
            }

            // Check if the specific node is selectable
            let nodeData;
            try {
                nodeData = JSON.parse(nodeElement.dataset.nodeData);
            } catch (e) {
                console.error("Quercus.js: Error parsing node data for selection check:", e);
                return; // Cannot proceed if data is malformed
            }
            if (nodeData.selectable === false) {
                console.warn(`Quercus.js: Node '${nodeData[this.options.nodeNameKey] || nodeData.name}' (ID: ${nodeData.id}) is not selectable.`);
                return;
            }

            const checkbox = nodeElement.querySelector('.treeview-checkbox');

            // Determine effective `isSelected` state if not explicitly passed
            if (isSelected === null) {
                isSelected = !this.selectedNodes.has(nodeElement); // Default toggle behavior for non-checkbox clicks
                if (checkbox) {
                    // If a checkbox exists, its state should drive the 'isSelected' for consistency
                    isSelected = checkbox.checked;
                }
            }

            // Case 1: Cascading selection (behavior depends on multiSelectEnabled)
            if (this.options.cascadeSelectChildren) {
                if (isSelected) {
                    // If multi-select is enabled, add selected node and its descendants to the selection
                    if (this.options.multiSelectEnabled) {
                        const descendants = this._getAllDescendants(nodeElement);
                        [nodeElement, ...descendants].forEach(targetLi => {
                            let targetNodeData;
                            try {
                                targetNodeData = JSON.parse(targetLi.dataset.nodeData);
                            } catch (e) {
                                console.error("Quercus.js: Error parsing descendant node data for selection:", e);
                                return;
                            }
                            // Only select if the target node itself is selectable
                            if (targetNodeData.selectable === undefined || targetNodeData.selectable === true) {
                                this.selectedNodes.add(targetLi);
                                targetLi.classList.add('selected');
                                const targetCheckbox = targetLi.querySelector('.treeview-checkbox');
                                if (targetCheckbox) targetCheckbox.checked = true;
                            }
                        });
                    }
                    // If multi-select is NOT enabled, it's a single-select cascade: clear all, then select node and its descendants
                    else {
                        this.selectedNodes.forEach(node => node.classList.remove('selected'));
                        this.selectedNodes.clear();
                        if (this.options.checkboxSelectionEnabled) {
                            this.treeviewContainer.querySelectorAll('.treeview-checkbox').forEach(cb => cb.checked = false);
                        }

                        this.selectedNodes.add(nodeElement);
                        nodeElement.classList.add('selected');
                        if (checkbox) checkbox.checked = true;

                        const descendants = this._getAllDescendants(nodeElement);
                        descendants.forEach(descendantLi => {
                            let descNodeData;
                            try {
                                descNodeData = JSON.parse(descendantLi.dataset.nodeData);
                            } catch (e) {
                                console.error("Quercus.js: Error parsing descendant node data for selection:", e);
                                return;
                            }
                            // Only select if the descendant node itself is selectable
                            if (descNodeData.selectable === undefined || descNodeData.selectable === true) {
                                this.selectedNodes.add(descendantLi);
                                descendantLi.classList.add('selected');
                                const descCheckbox = descendantLi.querySelector('.treeview-checkbox');
                                if (descCheckbox) descCheckbox.checked = true;
                            }
                        });
                    }
                } else { // Deselecting in cascading mode
                    // Deselect the clicked node and all its descendants
                    const descendants = this._getAllDescendants(nodeElement);
                    [nodeElement, ...descendants].forEach(targetLi => {
                        this.selectedNodes.delete(targetLi);
                        targetLi.classList.remove('selected');
                        const targetCheckbox = targetLi.querySelector('.treeview-checkbox');
                        if (targetCheckbox) targetCheckbox.checked = false;
                    });
                }
            }
            // Case 2: Standard multi-select (no cascading)
            else if (this.options.multiSelectEnabled) {
                if (isSelected) {
                    this.selectedNodes.add(nodeElement);
                    nodeElement.classList.add('selected');
                    if (checkbox) checkbox.checked = true;
                } else {
                    this.selectedNodes.delete(nodeElement);
                    nodeElement.classList.remove('selected');
                    if (checkbox) checkbox.checked = false;
                }
            }
            // Case 3: Standard single-select (no cascading)
            else { // This means multiSelectEnabled is false and cascadeSelectChildren is false
                const wasAlreadySolelySelected = this.selectedNodes.has(nodeElement) && this.selectedNodes.size === 1;

                this.selectedNodes.forEach(node => node.classList.remove('selected'));
                this.selectedNodes.clear();
                if (this.options.checkboxSelectionEnabled) {
                    this.treeviewContainer.querySelectorAll('.treeview-checkbox').forEach(cb => cb.checked = false);
                }

                if (isSelected && !wasAlreadySolelySelected) {
                    this.selectedNodes.add(nodeElement);
                    nodeElement.classList.add('selected');
                    if (checkbox) checkbox.checked = true;
                }
            }

            this._triggerSelectionChange();
        }

        _triggerSelectionChange() {
            if (typeof this.options.onSelectionChange === 'function') {
                const selectedData = Array.from(this.selectedNodes).map(nodeElement => {
                    try {
                        return JSON.parse(nodeElement.dataset.nodeData);
                    } catch (e) {
                        console.error("Quercus.js: Error parsing selected node data:", e);
                        return {id: nodeElement.dataset.id, name: getDisplayNameFromNodeElement(nodeElement, this.options.nodeNameKey)}; // Use nodeNameKey
                    }
                });
                this.options.onSelectionChange(selectedData);
            }
            // Update Select All button text based on current selection state
            if (this.selectAllButton && this.options.multiSelectEnabled && this.options.nodeSelectionEnabled && !this.options.cascadeSelectChildren) {
                // Filter for only selectable nodes for comparison
                const allTrulySelectableNodes = Array.from(this.treeviewContainer.querySelectorAll('li')).filter(li => {
                    try {
                        const nodeData = JSON.parse(li.dataset.nodeData);
                        return nodeData.selectable === undefined || nodeData.selectable === true;
                    } catch (e) {
                        return false; // If data is malformed, treat as not selectable for this count
                    }
                });

                const isAllSelected = allTrulySelectableNodes.length > 0 && this.selectedNodes.size === allTrulySelectableNodes.length;
                this.selectAllButton.textContent = isAllSelected ? 'Deselect All' : 'Select All';
            }
        }

        _expandToShowSelected() {
            const allListItems = this.treeviewContainer.querySelectorAll('li');
            var ancestorsToExpand = new Set();

            this.selectedNodes.forEach(node => {
                node.classList.remove('hidden');
                let current = node;
                while(current.parentElement && current.parentElement.tagName === 'UL') {
                    current.parentElement.classList.remove('hidden-by-parent');
                    current = current.parentElement;
                }

                let parentUL = node.closest('ul');
                while (parentUL && parentUL !== this.treeviewContainer) {
                    const parentLI = parentUL.closest('li');
                    if (parentLI) {
                        ancestorsToExpand.add(parentLI);
                    }
                    parentUL = parentUL.parentElement.closest('ul');
                }
            });

            ancestorsToExpand.forEach(ancestor => {
                ancestor.classList.remove('hidden');
                ancestor.classList.add('expanded');
                const childUl = ancestor.querySelector('ul');
                const expander = ancestor.querySelector('.treeview-expander');
                if (childUl) {
                    childUl.style.height = 'auto'; // Expand ancestors
                }
                if (expander) expander.textContent = '-';
            });
        }

        _searchTree(searchTerm) {
            const allListItems = this.treeviewContainer.querySelectorAll('li');
            const expandableListItems = this.treeviewContainer.querySelectorAll('li.has-children');

            if (searchTerm === '') {
                // Restore nodes to their initial expansion state
                allListItems.forEach(item => {
                    item.classList.remove('hidden', 'highlight'); // Remove search-related classes
                });

                expandableListItems.forEach(item => {
                    const nodeId = item.dataset.id;
                    const expander = item.querySelector('.treeview-expander');
                    const childUl = item.querySelector('ul');

                    if (this._initialExpansionStates.has(nodeId)) {
                        const wasExpanded = this._initialExpansionStates.get(nodeId);
                        if (wasExpanded) {
                            item.classList.add('expanded');
                            if (expander) expander.textContent = '-';
                            if (childUl) childUl.style.height = 'auto';
                        } else {
                            // Only trigger collapse animation if it's currently expanded and should be collapsed
                            if (item.classList.contains('expanded')) {
                                item.classList.remove('expanded');
                                if (expander) expander.textContent = '+';
                                if (childUl) {
                                    childUl.style.height = `${childUl.scrollHeight}px`;
                                    requestAnimationFrame(() => {
                                        childUl.style.height = '0px';
                                    });
                                    childUl.addEventListener('transitionend', function handler() {
                                        childUl.removeEventListener('transitionend', handler);
                                        childUl.style.height = '';
                                    }, { once: true });
                                }
                            } else {
                                // If it was already collapsed, just ensure height is reset
                                if (childUl) childUl.style.height = '';
                            }
                        }
                    } else {
                        // Fallback to initiallyExpanded if state wasn't captured (e.g., node added after search)
                        if (this.options.initiallyExpanded) {
                            item.classList.add('expanded');
                            if (expander) expander.textContent = '-';
                            if (childUl) childUl.style.height = 'auto';
                        } else {
                            item.classList.remove('expanded');
                            if (expander) expander.textContent = '+';
                            if (childUl) childUl.style.height = '';
                        }
                    }
                });
                this._initialExpansionStates.clear(); // Clear stored states after restoring
                return; // Exit the function after resetting
            }

            // Store current expansion states before modifying for search
            if (this._initialExpansionStates.size === 0) { // Only store if not already stored from an active search
                expandableListItems.forEach(item => {
                    const nodeId = item.dataset.id;
                    this._initialExpansionStates.set(nodeId, item.classList.contains('expanded'));
                });
            }

            // fix width so it doesn't jump around if the search results are narrower than hidden options
            if (this.treeviewContainer.style.width == '') {
                this.treeviewContainer.style.width = this.treeviewContainer.offsetWidth + 'px';
            }

            const matchingNodes = new Set();
            const ancestorsToExpand = new Set();

            allListItems.forEach(item => {
                item.classList.remove('highlight');
                item.classList.add('hidden'); // Hide all initially for search
                const childUl = item.querySelector('ul');
                const expander = item.querySelector('.treeview-expander');
                if (childUl) {
                    childUl.style.height = '0px'; // Collapse children for search
                    item.classList.remove('expanded');
                    if (expander) expander.textContent = '+';
                }
            });

            allListItems.forEach(item => {
                const nodeData = JSON.parse(item.dataset.nodeData);
                const searchableText = nodeData[this.options.nodeNameKey] || nodeData.name || ''; // Use nodeNameKey for search

                if (searchableText.toLowerCase().includes(searchTerm.toLowerCase())) {
                    matchingNodes.add(item);
                    item.classList.add('highlight');
                    let parentUL = item.closest('ul');
                    while (parentUL && parentUL !== this.treeviewContainer) {
                        const parentLI = parentUL.closest('li');
                        if (parentLI) {
                            ancestorsToExpand.add(parentLI);
                        }
                        parentUL = parentUL.parentElement.closest('ul');
                    }
                    if (this.options.showChildrenOnSearch) {
                        for( const child of item.getElementsByTagName('li') ) {
                            matchingNodes.add(child);
                        }
                    }
                }
            });

            matchingNodes.forEach(node => {
                node.classList.remove('hidden');
                let current = node;
                while(current.parentElement && current.parentElement.tagName === 'UL') {
                    current.parentElement.classList.remove('hidden-by-parent');
                    current = current.parentElement;
                }
            });

            ancestorsToExpand.forEach(ancestor => {
                ancestor.classList.remove('hidden');
                ancestor.classList.add('expanded');
                const childUl = ancestor.querySelector('ul');
                const expander = ancestor.querySelector('.treeview-expander');
                if (childUl) {
                    childUl.style.height = 'auto'; // Expand ancestors
                }
                if (expander) expander.textContent = '-';
            });
        }

        // Public method to set new data
        setData(newData) {
            this.options.data = newData;
            this.treeviewContainer.innerHTML = '';
            this.selectedNodes.clear();
            this._initialExpansionStates.clear(); // Clear stored states on new data
            this._createControls(); // Re-create search bar and buttons
            this._renderTree(this.options.data, this.treeviewContainer);

            // Re-apply initial expansion state after setData
            if (this.options.initiallyExpanded) {
                this.treeviewContainer.querySelectorAll('li.has-children').forEach(li => {
                    li.classList.add('expanded');
                    const expander = li.querySelector('.treeview-expander');
                    if (expander) expander.textContent = '-';
                    const childUl = li.querySelector('ul');
                    if (childUl) {
                        childUl.style.height = 'auto';
                    }
                });
            } else {
                this.treeviewContainer.querySelectorAll('li.has-children').forEach(li => {
                    li.classList.remove('expanded');
                    const expander = li.querySelector('.treeview-expander');
                    if (expander) expander.textContent = '+';
                    const childUl = li.querySelector('ul');
                    if (childUl) {
                        childUl.style.height = '';
                    }
                });
            }


            // Re-apply initial selections if any after setData
            if (this.options.nodeSelectionEnabled) {
                const initiallySelectedNodeIds = [];
                const findInitiallySelected = (nodes) => {
                    nodes.forEach(node => {
                        // Only consider nodes that are explicitly selectable or default to selectable
                        if (node.selected && (node.selectable === undefined || node.selectable === true)) {
                            initiallySelectedNodeIds.push(node.id);
                        }
                        if (node.children) {
                            findInitiallySelected(node.children);
                        }
                    });
                };
                findInitiallySelected(this.options.data);

                initiallySelectedNodeIds.forEach(id => {
                    const nodeElement = this.treeviewContainer.querySelector(`[data-id="${id}"]`);
                    if (nodeElement) {
                        this._selectNode(nodeElement, true);
                    }
                });
            }
        }

        /**
         * Public method to programmatically select or deselect a node by its ID.
         * @param {string} id The ID of the node to select/deselect.
         * @param {boolean} [shouldSelect=true] True to select the node, false to deselect.
         */
        selectNodeById(id, shouldSelect = true) {
            if (!this.options.nodeSelectionEnabled) {
                console.warn(`Quercus.js: Cannot programmatically select node '${id}'. Node selection is disabled.`);
                return;
            }
            const nodeElement = this.treeviewContainer.querySelector(`[data-id="${id}"]`);
            if (nodeElement) {
                let nodeData;
                try {
                    nodeData = JSON.parse(nodeElement.dataset.nodeData);
                } catch (e) {
                    console.error("Quercus.js: Error parsing node data for programmatic selection check:", e);
                    return;
                }
                if (nodeData.selectable === false && shouldSelect) {
                    console.warn(`Quercus.js: Node '${nodeData[this.options.nodeNameKey] || nodeData.name}' (ID: ${nodeData.id}) is not selectable; cannot select programmatically.`);
                    return;
                }
                this._selectNode(nodeElement, shouldSelect);
            } else {
                console.warn(`Quercus.js: Node with ID '${id}' not found for programmatic selection.`);
            }
        }

        getSelectedNode() {
            return this.getSelectedNodes();
        }

        getSelectedNodes() {
            const selectedData = Array.from(this.selectedNodes).map(nodeElement => {
                try {
                    return JSON.parse(nodeElement.dataset.nodeData);
                } catch (e) {
                        console.error("Quercus.js: Error parsing selected node data:", e);
                        return {id: nodeElement.dataset.id, name: getDisplayNameFromNodeElement(nodeElement, this.options.nodeNameKey)}; // Use nodeNameKey
                }
            });
            return selectedData;
        }

        search(searchTerm) {
            if (this.options.searchEnabled && this.treeSearchInput) {
                this.treeSearchInput.value = searchTerm;
            }
            this._searchTree(searchTerm);
        }

        expandToShowSelected() {
            this._expandToShowSelected();
        }

        /**
         * Returns an array of values for a specified key from all selected nodes and their recursive children.
         * @param {string} key The key whose values are to be extracted.
         * @returns {Array<any>} An array of values found. Duplicates are included.
         */
        getSelectedNodesAndChildrenValues(key) {
            const values = [];
            const processedNodes = new Set(); // To prevent infinite loops with circular references or re-processing

            // Helper to recursively collect values
            const collectValues = (nodeElement) => {
                if (!nodeElement || processedNodes.has(nodeElement)) {
                    return;
                }
                processedNodes.add(nodeElement);

                try {
                    const nodeData = JSON.parse(nodeElement.dataset.nodeData);
                    if (nodeData && Object.prototype.hasOwnProperty.call(nodeData, key)) {
                        values.push(nodeData[key]);
                    }
                } catch (e) {
                    console.error("Quercus.js: Error parsing node data for value collection:", e);
                }

                // Recursively get children and process them
                const childUl = nodeElement.querySelector('ul');
                if (childUl) {
                    Array.from(childUl.children).forEach(childLi => {
                        collectValues(childLi);
                    });
                }
            };

            // Start with selected nodes
            this.selectedNodes.forEach(selectedNodeElement => {
                collectValues(selectedNodeElement);
            });

            return values;
        }

        /**
         * Inverts the selection state of all selectable nodes in the tree.
         * If a node is currently selected, it becomes deselected, and vice-versa.
         * This operation is only available if multiSelectEnabled is true and cascadeSelectChildren is false.
         */
        invertSelection() {
            if (!this.options.nodeSelectionEnabled) {
                console.warn("Quercus.js: Node selection is disabled, cannot invert selection.");
                return;
            }
            if (!this.options.multiSelectEnabled) {
                console.warn("Quercus.js: Invert Selection requires multi-select to be enabled.");
                return;
            }
            if (this.options.cascadeSelectChildren) {
                console.warn("Quercus.js: Invert Selection is not applicable when cascading selection is enabled.");
                return;
            }

            const allSelectableNodes = Array.from(this.treeviewContainer.querySelectorAll('li')).filter(li => {
                try {
                    const nodeData = JSON.parse(li.dataset.nodeData);
                    return nodeData.selectable === undefined || nodeData.selectable === true;
                } catch (e) {
                    console.error("Quercus.js: Error parsing node data for selectable check during invert:", e);
                    return false; // Treat as not selectable if data is malformed
                }
            });

            const nodesToSelect = [];
            const nodesToDeselect = [];

            allSelectableNodes.forEach(li => {
                if (this.selectedNodes.has(li)) {
                    nodesToDeselect.push(li);
                } else {
                    nodesToSelect.push(li);
                }
            });

            // Perform deselection first
            nodesToDeselect.forEach(li => {
                this.selectedNodes.delete(li);
                li.classList.remove('selected');
                const checkbox = li.querySelector('.treeview-checkbox');
                if (checkbox) checkbox.checked = false;
            });

            // Then perform selection
            nodesToSelect.forEach(li => {
                this.selectedNodes.add(li);
                li.classList.add('selected');
                const checkbox = li.querySelector('.treeview-checkbox');
                if (checkbox) checkbox.checked = true;
            });

            // Update the "Select All" button text if it's present
            if (this.selectAllButton && this.options.multiSelectEnabled && this.options.nodeSelectionEnabled && !this.options.cascadeSelectChildren) {
                const isAllSelected = allSelectableNodes.length > 0 && this.selectedNodes.size === allSelectableNodes.length;
                this.selectAllButton.textContent = isAllSelected ? 'Deselect All' : 'Select All';
            }

            this._triggerSelectionChange();
        }
    }



    // Expose the Treeview class to the global scope (window)
    window.Treeview = Treeview;

})();
