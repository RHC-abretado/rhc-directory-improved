// directory_widget.js - JavaScript widget for embedding staff directory
(function() {
    'use strict';
    
    // Default configuration
    const defaultConfig = {
        apiUrl: '', // Set this to your embed_directory.php URL
        containerId: 'staff-directory-widget',
        department: '',
        sections: 'both', // 'departments', 'staff', or 'both'
        theme: 'minimal',
        autoRefresh: 0, // Minutes (0 = disabled)
        showLastUpdated: true,
        loadingText: 'Loading directory...',
        errorText: 'Unable to load directory. Please try again later.',
        emptyText: 'No staff members found.',
        showSearch: false
    };
    
    // Widget class
    function StaffDirectoryWidget(config) {
        this.config = Object.assign({}, defaultConfig, config);
        this.container = null;
        this.data = null;
        this.searchTerm = '';
        
        this.init();
    }
    
    StaffDirectoryWidget.prototype.init = function() {
        this.container = document.getElementById(this.config.containerId);
        if (!this.container) {
            console.error('Staff Directory Widget: Container element not found');
            return;
        }
        
        this.loadDirectory();
        
        // Set up auto-refresh if enabled
        if (this.config.autoRefresh > 0) {
            setInterval(() => {
                this.loadDirectory();
            }, this.config.autoRefresh * 60000);
        }
    };
    
    StaffDirectoryWidget.prototype.loadDirectory = function() {
        this.showLoading();
        
        const url = new URL(this.config.apiUrl);
        url.searchParams.set('format', 'json');
        url.searchParams.set('dept', this.config.department);
        url.searchParams.set('sections', this.config.sections);
        
        fetch(url.toString())
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                this.data = data;
                this.render();
            })
            .catch(error => {
                console.error('Error loading directory:', error);
                this.showError();
            });
    };
    
    StaffDirectoryWidget.prototype.showLoading = function() {
        this.container.innerHTML = `
            <div class="staff-directory-loading">
                <div class="loading-spinner"></div>
                <p>${this.config.loadingText}</p>
            </div>
        `;
    };
    
    StaffDirectoryWidget.prototype.showError = function() {
        this.container.innerHTML = `
            <div class="staff-directory-error">
                <p>${this.config.errorText}</p>
                <button onclick="window.staffDirectoryWidget.loadDirectory()">Retry</button>
            </div>
        `;
    };
    
    StaffDirectoryWidget.prototype.render = function() {
        if (!this.data) return;
        
        let html = '<div class="staff-directory-widget">';
        
        // Add styles
        html += this.getStyles();
        
        // Add search if enabled
        if (this.config.showSearch) {
            html += this.renderSearch();
        }
        
        // Render departments section
        if (this.config.sections === 'departments' || this.config.sections === 'both') {
            html += this.renderDepartments();
        }
        
        // Render staff section
        if (this.config.sections === 'staff' || this.config.sections === 'both') {
            html += this.renderStaff();
        }
        
        // Add last updated
        if (this.config.showLastUpdated) {
            html += `<div class="directory-updated">Last updated: ${new Date().toLocaleString()}</div>`;
        }
        
        html += '</div>';
        
        this.container.innerHTML = html;
        
        // Add search functionality if enabled
        if (this.config.showSearch) {
            this.attachSearchHandler();
        }
    };
    
    StaffDirectoryWidget.prototype.renderSearch = function() {
        return `
            <div class="directory-search">
                <input type="text" id="directory-search-input" placeholder="Search staff or departments..." />
                <button onclick="window.staffDirectoryWidget.clearSearch()">Clear</button>
            </div>
        `;
    };
    
    StaffDirectoryWidget.prototype.renderDepartments = function() {
        if (!this.data.departments || this.data.departments.length === 0) {
            return '<div class="directory-empty">No departments found.</div>';
        }
        
        let html = '<div class="directory-section"><div class="directory-title">Department Directory</div>';
        
        // Group departments by first letter
        const deptsByLetter = {};
        this.data.departments.forEach(dept => {
            if (this.matchesSearch(dept.department_name)) {
                const letter = dept.department_name.charAt(0).toUpperCase();
                if (!deptsByLetter[letter]) deptsByLetter[letter] = [];
                deptsByLetter[letter].push(dept);
            }
        });
        
        Object.keys(deptsByLetter).sort().forEach(letter => {
            html += `<div class="letter-header">${letter}</div>`;
            deptsByLetter[letter].forEach(dept => {
                const location = (dept.building && dept.room_number) ? 
                    ` <span class="location">(${dept.building}, ${dept.room_number})</span>` : '';
                html += `
                    <div class="dept-listing">
                        <div class="dept-name">${dept.department_name}${location}</div>
                        <div class="dept-phone">${dept.extension || 'N/A'}</div>
                    </div>
                `;
            });
        });
        
        html += '</div>';
        return html;
    };
    
    StaffDirectoryWidget.prototype.renderStaff = function() {
        if (!this.data.staff_by_department) {
            return '<div class="directory-empty">No staff found.</div>';
        }
        
        let html = '<div class="directory-section"><div class="directory-title">Staff Directory</div>';
        
        Object.keys(this.data.staff_by_department).forEach(deptName => {
            const staff = this.data.staff_by_department[deptName].filter(s => this.matchesSearch(s.name + ' ' + s.title));
            
            if (staff.length > 0) {
                html += `<div class="dept-header">${deptName}</div>`;
                staff.forEach(person => {
                    const room = person.room_number ? ` (${person.room_number})` : '';
                    html += `
                        <div class="staff-listing">
                            <div class="staff-info">
                                <div class="staff-name">${person.name}${room}</div>
                                <div class="staff-title">- ${person.title}</div>
                            </div>
                            <div class="staff-phone">${person.extension || 'N/A'}</div>
                        </div>
                    `;
                });
            }
        });
        
        html += '</div>';
        return html;
    };
    
    StaffDirectoryWidget.prototype.matchesSearch = function(text) {
        if (!this.searchTerm) return true;
        return text.toLowerCase().includes(this.searchTerm.toLowerCase());
    };
    
    StaffDirectoryWidget.prototype.attachSearchHandler = function() {
        const searchInput = document.getElementById('directory-search-input');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.searchTerm = e.target.value;
                this.render();
            });
        }
    };
    
    StaffDirectoryWidget.prototype.clearSearch = function() {
        this.searchTerm = '';
        const searchInput = document.getElementById('directory-search-input');
        if (searchInput) {
            searchInput.value = '';
        }
        this.render();
    };
    
    StaffDirectoryWidget.prototype.getStyles = function() {
        return `
            <style>
                .staff-directory-widget {
                    font-family: Arial, sans-serif;
                    font-size: 14px;
                    line-height: 1.4;
                    max-width: 100%;
                }
                .directory-title {
                    background: #007bff;
                    color: white;
                    padding: 8px 12px;
                    margin: 0 0 15px 0;
                    font-weight: bold;
                    text-transform: uppercase;
                }
                .directory-search {
                    margin-bottom: 15px;
                    padding: 10px;
                    background: #f8f9fa;
                    border-radius: 4px;
                }
                .directory-search input {
                    width: 70%;
                    padding: 6px 10px;
                    border: 1px solid #ddd;
                    border-radius: 3px;
                    margin-right: 10px;
                }
                .directory-search button {
                    padding: 6px 12px;
                    background: #6c757d;
                    color: white;
                    border: none;
                    border-radius: 3px;
                    cursor: pointer;
                }
                .letter-header {
                    font-weight: bold;
                    color: #007bff;
                    margin: 20px 0 10px 0;
                    font-size: 16px;
                }
                .dept-header {
                    font-weight: bold;
                    color: #007bff;
                    margin: 15px 0 8px 0;
                    padding-bottom: 4px;
                    border-bottom: 1px solid #007bff;
                }
                .dept-listing, .staff-listing {
                    display: grid;
                    grid-template-columns: 1fr auto;
                    gap: 15px;
                    padding: 6px 0;
                    border-bottom: 1px dotted #ddd;
                }
                .dept-phone, .staff-phone {
                    font-family: monospace;
                    text-align: right;
                    color: #666;
                }
                .staff-name {
                    font-weight: 500;
                }
                .staff-title {
                    color: #666;
                    font-style: italic;
                    margin-top: 2px;
                }
                .location {
                    color: #999;
                    font-size: 12px;
                }
                .directory-updated {
                    margin-top: 15px;
                    padding: 8px;
                    background: #f8f9fa;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                    border-radius: 3px;
                }
                .directory-empty {
                    text-align: center;
                    padding: 20px;
                    color: #666;
                    font-style: italic;
                }
                .staff-directory-loading {
                    text-align: center;
                    padding: 40px 20px;
                }
                .loading-spinner {
                    border: 4px solid #f3f3f3;
                    border-top: 4px solid #007bff;
                    border-radius: 50%;
                    width: 40px;
                    height: 40px;
                    animation: spin 1s linear infinite;
                    margin: 0 auto 15px;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                .staff-directory-error {
                    text-align: center;
                    padding: 20px;
                    color: #dc3545;
                }
                .staff-directory-error button {
                    margin-top: 10px;
                    padding: 8px 16px;
                    background: #007bff;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                }
                @media (max-width: 768px) {
                    .dept-listing, .staff-listing {
                        grid-template-columns: 1fr;
                        gap: 5px;
                    }
                    .dept-phone, .staff-phone {
                        text-align: left;
                    }
                    .directory-search input {
                        width: 65%;
                    }
                }
            </style>
        `;
    };
    
    // Global initialization function
    window.initStaffDirectory = function(config) {
        if (!config.apiUrl) {
            console.error('Staff Directory Widget: apiUrl is required');
            return;
        }
        window.staffDirectoryWidget = new StaffDirectoryWidget(config);
        return window.staffDirectoryWidget;
    };
    
})();