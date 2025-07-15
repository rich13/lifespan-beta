// Shared User Switcher JavaScript
// Can be used by both desktop and mobile versions

class UserSwitcher {
    constructor(containerId = 'userSwitcherList') {
        this.containerId = containerId;
        this.csrfToken = $('meta[name="csrf-token"]').attr('content');
        this.init();
    }

    init() {
        console.log('UserSwitcher initialized for container:', this.containerId);
    }

    loadUserList() {
        console.log('Loading user switcher list...');
        console.log('URL:', window.routes.userSwitcher.users);
        
        const $container = $(`#${this.containerId}`);
        if (!$container.length) {
            console.error('User switcher container not found:', this.containerId);
            return;
        }

        $.ajax({
            url: window.routes.userSwitcher.users,
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken,
                'Accept': 'application/json'
            }
        })
        .done((response) => {
            console.log('User switcher response:', response);
            this.displayUsers(response);
        })
        .fail((xhr) => {
            console.error('Failed to load user switcher list:', xhr.responseText);
            console.error('Status:', xhr.status);
            $container.html('<div class="text-danger small">Failed to load users</div>');
        });
    }

    displayUsers(response) {
        const $container = $(`#${this.containerId}`);
        $container.empty();
        
        // Handle both array response and object with users property
        const users = Array.isArray(response) ? response : (response.users || []);
        console.log('Processed users:', users);
        
        if (users.length > 0) {
            users.forEach((user) => {
                console.log('Creating user item for:', user);
                
                // Skip the current user (unless it's a switch back option)
                if (user.is_current && !user.is_switch_back) {
                    console.log('Skipping current user:', user.email);
                    return;
                }
                
                const userItem = this.createUserItem(user);
                $container.append(userItem);
            });
        } else {
            $container.append('<div class="text-muted small">No users found</div>');
        }
    }

    createUserItem(user) {
        let buttonClass = 'btn btn-outline-secondary btn-sm w-100 text-start';
        let buttonHtml = '';
        
        // Special styling for "Switch back" option
        if (user.is_switch_back) {
            buttonClass = 'btn btn-outline-primary btn-sm w-100 text-start';
            buttonHtml = '<i class="bi bi-arrow-return-left me-2"></i>' + user.email;
        } else {
            buttonHtml = user.email;
            
            // Add indicators
            if (user.is_current) {
                buttonHtml += ' <span class="badge bg-info ms-1">Current</span>';
                buttonClass += ' disabled';
            }
            
            if (user.is_admin_user) {
                buttonHtml += ' <span class="badge bg-warning ms-1">Admin</span>';
            }
        }
        
        return $(`
            <form method="POST" action="${window.routes.userSwitcher.switch.replace(':userId', user.id)}" class="mb-2">
                <input type="hidden" name="_token" value="${this.csrfToken}">
                <button type="submit" class="${buttonClass}">
                    ${buttonHtml}
                </button>
            </form>
        `);
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = UserSwitcher;
} else {
    window.UserSwitcher = UserSwitcher;
} 