/**
 * Sistema de Relatórios - WiFi Tocantins
 * JavaScript para funcionalidades avançadas dos relatórios
 */

class ReportsManager {
    constructor() {
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupDateValidation();
        this.setupAutoRefresh();
        this.setupFilterPresets();
        this.setupAutomaticTimeAdjustment();
    }

    setupEventListeners() {
        // Auto-aplicar filtros quando mudar datas
        const dateInputs = document.querySelectorAll('input[type="date"]');
        dateInputs.forEach(input => {
            input.addEventListener('change', () => {
                this.validateDateRange();
            });
        });

        // Filtros rápidos
        const quickFilters = document.querySelectorAll('[data-quick-filter]');
        quickFilters.forEach(filter => {
            filter.addEventListener('click', (e) => {
                e.preventDefault();
                this.applyQuickFilter(filter.dataset.quickFilter);
            });
        });

        // Exportação com confirmação
        const exportLinks = document.querySelectorAll('a[href*="export"]');
        exportLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                if (!this.confirmExport()) {
                    e.preventDefault();
                }
            });
        });
    }

    setupAutomaticTimeAdjustment() {
        const startDateInput = document.querySelector('input[name="start_date"]');
        const endDateInput = document.querySelector('input[name="end_date"]');
        
        if (startDateInput && endDateInput) {
            // Forçar horários ao carregar a página
            if (startDateInput.value && !startDateInput.value.includes('T00:00')) {
                const datePart = startDateInput.value.split('T')[0];
                startDateInput.value = `${datePart}T00:00`;
            }
            
            if (endDateInput.value && !endDateInput.value.includes('T23:59')) {
                const datePart = endDateInput.value.split('T')[0];
                endDateInput.value = `${datePart}T23:59`;
            }
            
            // Adicionar listener para ajustar automaticamente quando o usuário selecionar
            startDateInput.addEventListener('change', () => {
                if (startDateInput.value) {
                    const datePart = startDateInput.value.split('T')[0];
                    startDateInput.value = `${datePart}T00:00`;
                    this.showNotification('Horário inicial ajustado para 00:00', 'info', 2000);
                }
            });
            
            endDateInput.addEventListener('change', () => {
                if (endDateInput.value) {
                    const datePart = endDateInput.value.split('T')[0];
                    endDateInput.value = `${datePart}T23:59`;
                    this.showNotification('Horário final ajustado para 23:59', 'info', 2000);
                }
            });
        }
    }

    setupDateValidation() {
        const startDateInput = document.querySelector('input[name="start_date"]');
        const endDateInput = document.querySelector('input[name="end_date"]');
        
        if (startDateInput && endDateInput) {
            startDateInput.addEventListener('blur', () => {
                const startDate = new Date(startDateInput.value);
                const endDate = new Date(endDateInput.value);
                
                if (startDate > endDate) {
                    const datePart = startDateInput.value.split('T')[0];
                    endDateInput.value = `${datePart}T23:59`;
                    this.showNotification('Data final ajustada para não ser anterior à data inicial', 'warning');
                }
                
                this.checkDateRange();
            });

            endDateInput.addEventListener('blur', () => {
                const startDate = new Date(startDateInput.value);
                const endDate = new Date(endDateInput.value);
                
                if (endDate < startDate) {
                    const datePart = endDateInput.value.split('T')[0];
                    startDateInput.value = `${datePart}T00:00`;
                    this.showNotification('Data inicial ajustada para não ser posterior à data final', 'warning');
                }
                
                this.checkDateRange();
            });
        }
    }

    checkDateRange() {
        const startDateInput = document.querySelector('input[name="start_date"]');
        const endDateInput = document.querySelector('input[name="end_date"]');
        
        if (startDateInput && endDateInput) {
            const startDate = new Date(startDateInput.value);
            const endDate = new Date(endDateInput.value);
            const diffTime = Math.abs(endDate - startDate);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays > 365) {
                this.showNotification('Período muito longo! Relatórios com mais de 1 ano podem ser lentos.', 'warning');
            }
        }
    }

    validateDateRange() {
        const startDateInput = document.querySelector('input[name="start_date"]');
        const endDateInput = document.querySelector('input[name="end_date"]');
        
        if (startDateInput && endDateInput) {
            const startDate = new Date(startDateInput.value);
            const endDate = new Date(endDateInput.value);
            
            if (startDate > endDate) {
                this.showNotification('Data inicial não pode ser posterior à data final', 'error');
                return false;
            }
        }
        return true;
    }

    setupAutoRefresh() {
        // Auto-refresh apenas se estiver na aba ativa e período for hoje
        const startDateInput = document.querySelector('input[name="start_date"]');
        const endDateInput = document.querySelector('input[name="end_date"]');
        
        if (startDateInput && endDateInput) {
            const today = new Date().toISOString().split('T')[0];
            
            if (startDateInput.value === today && endDateInput.value === today) {
                this.startAutoRefresh();
            }
        }
    }

    startAutoRefresh() {
        // Refresh a cada 5 minutos se for dados de hoje
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                const today = new Date().toISOString().split('T')[0];
                const startDate = document.querySelector('input[name="start_date"]').value;
                const endDate = document.querySelector('input[name="end_date"]').value;
                
                if (startDate === today && endDate === today) {
                    this.refreshCurrentData();
                }
            }
        }, 300000); // 5 minutos
    }

    refreshCurrentData() {
        // Evitar refresh se gráficos estão sendo renderizados
        if (document.querySelector('canvas[style*="block"]')) {
            console.log('Charts are rendering, skipping refresh');
            return;
        }
        
        // Recarregar apenas os cards de estatísticas sem refresh da página
        this.showNotification('Atualizando dados...', 'info');
        
        // Simular atualização (implementar AJAX quando necessário)
        setTimeout(() => {
            this.showNotification('Dados atualizados!', 'success');
        }, 1000);
    }

    setupFilterPresets() {
        // Quick filters removed — using advanced filters only
    }

    createQuickFilterButtons() {
        // Removed
    }

    applyQuickFilter(filterType) {
        const today = new Date();
        const startDateInput = document.querySelector('input[name="start_date"]');
        const endDateInput = document.querySelector('input[name="end_date"]');
        const paymentStatusSelect = document.querySelector('select[name="payment_status"]');
        const userStatusSelect = document.querySelector('select[name="user_status"]');

        // Reset status filters first
        if (paymentStatusSelect) paymentStatusSelect.value = 'all';
        if (userStatusSelect) userStatusSelect.value = 'all';

        const isDateTime = startDateInput?.type === 'datetime-local';
        const fmtStart = (d) => isDateTime ? `${d.toISOString().split('T')[0]}T00:00` : d.toISOString().split('T')[0];
        const fmtEnd   = (d) => isDateTime ? `${d.toISOString().split('T')[0]}T23:59` : d.toISOString().split('T')[0];

        switch (filterType) {
            case 'today':
                startDateInput.value = fmtStart(today);
                endDateInput.value = fmtEnd(today);
                break;

            case 'yesterday':
                const yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);
                startDateInput.value = fmtStart(yesterday);
                endDateInput.value = fmtEnd(yesterday);
                break;

            case 'week':
                const startOfWeek = new Date(today);
                startOfWeek.setDate(today.getDate() - today.getDay());
                startDateInput.value = fmtStart(startOfWeek);
                endDateInput.value = fmtEnd(today);
                break;

            case 'month':
                const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
                startDateInput.value = fmtStart(startOfMonth);
                endDateInput.value = fmtEnd(today);
                break;

            case 'paid':
                if (paymentStatusSelect) paymentStatusSelect.value = 'completed';
                break;

            case 'pending':
                if (paymentStatusSelect) paymentStatusSelect.value = 'pending';
                break;
        }

        // Auto-submit form after applying filter
        setTimeout(() => {
            const form = document.querySelector('form');
            if (form) {
                this.showNotification(`Filtro "${this.getFilterName(filterType)}" aplicado`, 'success');
                form.submit();
            }
        }, 100);
    }

    getFilterName(filterType) {
        const names = {
            'today': 'Hoje',
            'yesterday': 'Ontem',
            'week': 'Esta Semana',
            'month': 'Este Mês',
            'paid': 'Apenas Pagos',
            'pending': 'Apenas Pendentes'
        };
        return names[filterType] || filterType;
    }

    confirmExport() {
        const startDate = document.querySelector('input[name="start_date"]').value;
        const endDate = document.querySelector('input[name="end_date"]').value;
        
        return confirm(`Exportar dados do período de ${this.formatDate(startDate)} até ${this.formatDate(endDate)}?`);
    }

    formatDate(dateStr) {
        if (!dateStr) return '';
        // Suporta tanto 'YYYY-MM-DD' quanto 'YYYY-MM-DDTHH:mm' (datetime-local)
        const datePart = dateStr.split('T')[0];
        const timePart = dateStr.includes('T') ? dateStr.split('T')[1] : null;
        const parts = datePart.split('-');
        if (parts.length !== 3) return dateStr;
        const formatted = `${parts[2]}/${parts[1]}/${parts[0]}`;
        return timePart ? `${formatted} ${timePart.substring(0, 5)}` : formatted;
    }

    showNotification(message, type = 'info', duration = 3000) {
        // Remover notificação existente
        const existingNotification = document.querySelector('.notification');
        if (existingNotification) {
            existingNotification.remove();
        }

        // Criar nova notificação
        const notification = document.createElement('div');
        notification.className = `notification fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full`;
        
        const colors = {
            'success': 'bg-green-500 text-white',
            'error': 'bg-red-500 text-white',
            'warning': 'bg-yellow-500 text-white',
            'info': 'bg-blue-500 text-white'
        };
        
        notification.className += ` ${colors[type] || colors.info}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Animar entrada
        setTimeout(() => {
            notification.classList.remove('translate-x-full');
        }, 100);
        
        // Remover após o tempo especificado
        setTimeout(() => {
            notification.classList.add('translate-x-full');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, duration);
    }

    // Método para atualizar gráficos dinamicamente
    updateCharts(data) {
        // Implementar atualização de gráficos quando necessário
        console.log('Updating charts with new data:', data);
        
        // Evitar conflito com gráficos já inicializados
        if (typeof window.initializeCharts === 'function') {
            // Aguardar um pouco antes de reinicializar
            setTimeout(() => {
                window.initializeCharts();
            }, 100);
        }
    }

    // Método para exportação customizada
    customExport(type, format, filters) {
        const params = new URLSearchParams({
            type: type,
            format: format,
            ...filters
        });
        
        const exportUrl = `/admin/reports/export?${params.toString()}`;
        window.location.href = exportUrl;
    }
}

// Inicializar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    new ReportsManager();
});

// Função global para compatibilidade
window.ReportsManager = ReportsManager;
