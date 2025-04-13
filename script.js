$(document).ready(function() {
    // Global variables
    let currentPage = 1;
    let totalPages = 0;
    let currentResults = [];
    let currentUrl = '';
    let currentType = '';
    let currentSelector = '';
    
    // Show/hide selector input based on scrape type selection
    $('input[name="scrapeType"]').change(function() {
        if ($(this).val() === 'custom') {
            $('#selectorContainer').slideDown();
        } else {
            $('#selectorContainer').slideUp();
        }
    });
    
    // Handle form submission
    $('#scrapeForm').submit(function(e) {
        e.preventDefault();
        
        // Reset pagination
        currentPage = 1;
        
        // Get form values
        currentUrl = $('#url').val().trim();
        currentType = $('input[name="scrapeType"]:checked').val();
        currentSelector = $('#selector').val().trim();
        
        // Validate URL
        if (!currentUrl) {
            showError('Please enter a valid URL');
            return;
        }
        
        // Validate selector if custom type is selected
        if (currentType === 'custom' && !currentSelector) {
            showError('Please enter a CSS selector');
            return;
        }
        
        // Clear previous results
        $('#resultsContainer').empty();
        $('#errorMessage').hide();
        $('#loadMoreContainer').hide();
        
        // Show loading indicator
        $('#loadingIndicator').show();
        
        // Perform scraping
        performScraping();
    });
    
    // Load more button click handler
    $('#loadMoreBtn').click(function() {
        if (currentPage < totalPages) {
            currentPage++;
            $('#loadingIndicator').show();
            performScraping(true);
        }
    });
    
    // Export as CSV
    $('#exportCSV').click(function() {
        if (currentResults.length === 0) {
            showError('No data to export');
            return;
        }
        
        let csvContent = '';
        
        // Handle different data types
        switch (currentType) {
            case 'title':
                csvContent = 'Title\n' + currentResults.join('\n');
                break;
                
            case 'images':
                csvContent = 'Image URL,Alt Text,Width,Height\n';
                currentResults.forEach(img => {
                    csvContent += `"${img.src}","${img.alt || ''}","${img.width || ''}","${img.height || ''}"\n`;
                });
                break;
                
            case 'tables':
                // Export first table only for simplicity
                if (currentResults.length > 0 && currentResults[0].headers) {
                    csvContent = currentResults[0].headers.join(',') + '\n';
                    currentResults[0].rows.forEach(row => {
                        csvContent += row.map(cell => `"${cell.replace(/"/g, '""')}"`).join(',') + '\n';
                    });
                }
                break;
                
            case 'custom':
                csvContent = 'Text Content,HTML\n';
                currentResults.forEach(item => {
                    csvContent += `"${item.text.replace(/"/g, '""')}","${item.html.replace(/"/g, '""')}"\n`;
                });
                break;
        }
        
        // Create download link
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', 'scrape_results.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
    
    // Export as JSON
    $('#exportJSON').click(function() {
        if (currentResults.length === 0) {
            showError('No data to export');
            return;
        }
        
        const jsonData = JSON.stringify(currentResults, null, 2);
        const blob = new Blob([jsonData], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', 'scrape_results.json');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
    
    // Infinite scroll implementation
    $(window).scroll(function() {
        if ($(window).scrollTop() + $(window).height() > $(document).height() - 200) {
            if ($('#loadMoreBtn').is(':visible') && !$('#loadingIndicator').is(':visible')) {
                $('#loadMoreBtn').click();
            }
        }
    });
    
    // Function to perform scraping
    function performScraping(append = false) {
        $.ajax({
            url: 'index.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'scrape',
                url: currentUrl,
                type: currentType,
                selector: currentSelector,
                page: currentPage
            },
            success: function(response) {
                $('#loadingIndicator').hide();
                
                if (response.error) {
                    showError(response.error);
                    return;
                }
                
                // Store results for export
                if (append) {
                    currentResults = currentResults.concat(response.results);
                } else {
                    currentResults = response.results;
                }
                
                // Update pagination info
                totalPages = response.pagination.totalPages;
                
                // Display results
                displayResults(response.results, append);
                
                // Show/hide load more button
                if (currentPage < totalPages) {
                    $('#loadMoreContainer').show();
                } else {
                    $('#loadMoreContainer').hide();
                }
            },
            error: function(xhr, status, error) {
                $('#loadingIndicator').hide();
                showError('An error occurred: ' + error);
            }
        });
    }
    
    // Function to display results
    function displayResults(results, append = false) {
        if (!append) {
            $('#resultsContainer').empty();
        }
        
        if (results.length === 0) {
            if (!append) {
                $('#resultsContainer').html(`
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>No results found</h3>
                        <p>Try a different URL or selector</p>
                    </div>
                `);
            }
            return;
        }
        
        let html = '';
        
        switch (currentType) {
            case 'title':
                html = `
                    <div class="card result-card fade-in">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-heading me-2"></i>Page Title</h5>
                            <p class="card-text fs-4">${results[0]}</p>
                        </div>
                    </div>
                `;
                break;
                
            case 'images':
                html = '<div class="row">';
                results.forEach(img => {
                    html += `
                        <div class="col-md-4 col-lg-3 mb-4 fade-in" style="animation-delay: ${Math.random() * 0.5}s">
                            <div class="card result-card h-100">
                                <div class="card-img-top p-2">
                                    <img src="${img.src}" alt="${img.alt || 'Image'}" class="image-result w-100" 
                                        onerror="this.onerror=null; this.src='https://via.placeholder.com/150?text=Image+Error';">
                                </div>
                                <div class="card-body">
                                    <p class="card-text small text-truncate" title="${img.src}">
                                        <strong>Source:</strong> ${img.src}
                                    </p>
                                    ${img.alt ? `<p class="card-text small"><strong>Alt:</strong> ${img.alt}</p>` : ''}
                                    ${img.width && img.height ? 
                                        `<p class="card-text small"><strong>Dimensions:</strong> ${img.width}×${img.height}</p>` : ''}
                                </div>
                                <div class="card-footer">
                                    <a href="${img.src}" class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="fas fa-external-link-alt me-1"></i> Open
                                    </a>
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                break;
                
            case 'tables':
                if (results.length === 0) {
                    html = `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No tables found on this page.
                        </div>
                    `;
                } else {
                    results.forEach((table, index) => {
                        html += `
                            <div class="card result-card mb-4 fade-in">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="fas fa-table me-2"></i>Table ${index + 1}</h5>
                                    <button class="btn btn-sm btn-outline-secondary copy-table-btn" data-table-index="${index}">
                                        <i class="fas fa-copy me-1"></i> Copy
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                        `;
                        
                        // Table headers
                        if (table.headers && table.headers.length > 0) {
                            html += '<thead><tr>';
                            table.headers.forEach(header => {
                                html += `<th>${header}</th>`;
                            });
                            html += '</tr></thead>';
                        }
                        
                        // Table rows
                        html += '<tbody>';
                        if (table.rows && table.rows.length > 0) {
                            table.rows.forEach(row => {
                                html += '<tr>';
                                row.forEach(cell => {
                                    html += `<td>${cell}</td>`;
                                });
                                html += '</tr>';
                            });
                        }
                        html += '</tbody></table></div></div></div>';
                    });
                }
                break;
                
            case 'custom':
                html = '<div class="row">';
                results.forEach((item, index) => {
                    html += `
                        <div class="col-md-6 mb-4 fade-in" style="animation-delay: ${index * 0.1}s">
                            <div class="card result-card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="fas fa-code me-2"></i>Element ${index + 1}</h5>
                                    <div>
                                        <button class="btn btn-sm btn-outline-secondary copy-html-btn" data-index="${index}">
                                            <i class="fas fa-copy me-1"></i> Copy HTML
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary ms-1 copy-text-btn" data-index="${index}">
                                            <i class="fas fa-copy me-1"></i> Copy Text
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <h6>Text Content:</h6>
                                        <div class="p-2 bg-light rounded">
                                            ${item.text.length > 200 ? item.text.substring(0, 200) + '...' : item.text}
                                        </div>
                                    </div>
                                    <div>
                                        <h6>HTML:</h6>
                                        <pre class="p-2 bg-light rounded small" style="max-height: 150px; overflow-y: auto;">${escapeHtml(item.html.substring(0, 500) + (item.html.length > 500 ? '...' : ''))}</pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                break;
        }
        
        if (append) {
            $('#resultsContainer').append(html);
        } else {
            $('#resultsContainer').html(html);
        }
        
        // Initialize copy buttons
        $('.copy-html-btn').click(function() {
            const index = $(this).data('index');
            copyToClipboard(currentResults[index].html);
            showToast('HTML copied to clipboard!');
        });
        
        $('.copy-text-btn').click(function() {
            const index = $(this).data('index');
            copyToClipboard(currentResults[index].text);
            showToast('Text copied to clipboard!');
        });
        
        $('.copy-table-btn').click(function() {
            const index = $(this).data('table-index');
            let tableText = '';
            
            // Headers
            if (currentResults[index].headers.length > 0) {
                tableText += currentResults[index].headers.join('\t') + '\n';
            }
            
            // Rows
            currentResults[index].rows.forEach(row => {
                tableText += row.join('\t') + '\n';
            });
            
            copyToClipboard(tableText);
            showToast('Table copied to clipboard!');
        });
    }
    
    // Helper function to show error message
    function showError(message) {
        $('#errorMessage').text(message).show();
        setTimeout(() => {
            $('#errorMessage').fadeOut();
        }, 5000);
    }
    
    // Helper function to show toast notification
    function showToast(message) {
        // Create toast element if it doesn't exist
        if ($('#toast-container').length === 0) {
            $('body').append(`
                <div id="toast-container" style="position: fixed; bottom: 20px; right: 20px; z-index: 1050;"></div>
            `);
        }
        
        const toastId = 'toast-' + Date.now();
        const toast = `
            <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
                <div class="toast-header">
                    <i class="fas fa-check-circle text-success me-2"></i>
                    <strong class="me-auto">Success</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            </div>
        `;
        
        $('#toast-container').append(toast);
        const toastElement = new bootstrap.Toast(document.getElementById(toastId));
        toastElement.show();
        
        // Remove toast after it's hidden
        $(`#${toastId}`).on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }
    
    // Helper function to copy text to clipboard
    function copyToClipboard(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'absolute';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }
    
    // Helper function to escape HTML
    function escapeHtml(html) {
        return html
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    
    // Initialize the page
    $(document).ready(function() {
        // Add animation to cards
        $('.result-card').hover(
            function() { $(this).addClass('shadow-lg'); },
            function() { $(this).removeClass('shadow-lg'); }
        );
    });
});