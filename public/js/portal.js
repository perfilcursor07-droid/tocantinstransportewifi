/**
 * Portal WiFi Tocantins - JavaScript
 * Sistema de conectividade para ônibus com Starlink
 */

class WiFiPortal {
    constructor() {
        this.deviceMac = '';
        this.deviceIp = '';
        this.connectionCheckInterval = null;
        this.paymentCheckInterval = null;
        this.loadingOverlay = null;
        this.paymentModal = null;
        this.registrationModal = null;
        this.currentUserId = null;
        this.currentPaymentId = null;
        this.pixTimerInterval = null;
        this.pixCountdownSeconds = 0;
        this.pixPaymentConfirmed = false;
        this.releaseCountdownInterval = null;
        this.releaseCountdownSeconds = 0;
        this.sessionDurationHours = window.SESSION_DURATION || 12; // Duração da sessão em horas
        this.init();
    }
    
    /**
     * Calcula o horário de expiração do acesso
     */
    calculateExpirationTime() {
        const now = new Date();
        const expiresAt = new Date(now.getTime() + (this.sessionDurationHours * 60 * 60 * 1000));
        
        const hours = expiresAt.getHours().toString().padStart(2, '0');
        const minutes = expiresAt.getMinutes().toString().padStart(2, '0');
        
        // Formatar data se for outro dia
        const today = new Date();
        if (expiresAt.getDate() !== today.getDate()) {
            const day = expiresAt.getDate().toString().padStart(2, '0');
            const month = (expiresAt.getMonth() + 1).toString().padStart(2, '0');
            return `${day}/${month} às ${hours}:${minutes}`;
        }
        
        return `${hours}:${minutes} horas`;
    }

    init() {
        this.setupElements();
        this.setupEventListeners();
        this.detectDevice();
        this.checkConnectionStatus();
    }

    setupElements() {
        this.loadingOverlay = document.getElementById('loading-overlay');
        this.paymentModal = document.getElementById('payment-modal');
        this.registrationModal = document.getElementById('registration-modal');
    }

    setupEventListeners() {
        // Botão principal de conectar (mobile)
        const connectBtn = document.getElementById('connect-btn');
        if (connectBtn) {
            connectBtn.addEventListener('click', () => this.handleConnectClick());
        }

        // Botão principal de conectar (desktop)
        const connectBtnDesktop = document.getElementById('connect-btn-desktop');
        if (connectBtnDesktop) {
            connectBtnDesktop.addEventListener('click', () => this.handleConnectClick());
        }


        // Fechar modais
        const closeModal = document.getElementById('close-modal');
        if (closeModal) {
            closeModal.addEventListener('click', () => this.hidePaymentModal());
        }

        const closeRegistrationModal = document.getElementById('close-registration-modal');
        if (closeRegistrationModal) {
            closeRegistrationModal.addEventListener('click', () => this.hideRegistrationModal());
        }

        // Formulário de registro
        const registrationForm = document.getElementById('registration-form');
        if (registrationForm) {
            registrationForm.addEventListener('submit', (e) => this.handleRegistrationSubmit(e));
        }

        // Botões voucher (desktop e mobile)
        const voucherBtn = document.getElementById('voucher-btn');
        if (voucherBtn) {
            voucherBtn.addEventListener('click', () => this.applyVoucher('voucher-code'));
        }

        const voucherBtnMobile = document.getElementById('voucher-btn-mobile');
        if (voucherBtnMobile) {
            voucherBtnMobile.addEventListener('click', () => this.applyVoucher('voucher-code-mobile'));
        }

        // Enter nos campos voucher
        const voucherInput = document.getElementById('voucher-code');
        if (voucherInput) {
            voucherInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.applyVoucher('voucher-code');
                }
            });
        }

        const voucherInputMobile = document.getElementById('voucher-code-mobile');
        if (voucherInputMobile) {
            voucherInputMobile.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.applyVoucher('voucher-code-mobile');
                }
            });
        }

        // Gerenciar conexão
        const manageBtn = document.getElementById('manage-connection');
        if (manageBtn) {
            manageBtn.addEventListener('click', () => this.showConnectionManager());
        }

        // Máscara de telefone (SIMPLIFICADO - sem verificação de usuário existente)
        const phoneInput = document.getElementById('user_phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', (e) => this.applyPhoneMask(e));
            phoneInput.addEventListener('keydown', (e) => this.handlePhoneKeydown(e));
        }

        // Email autocomplete com sugestões de domínio
        const emailInput = document.getElementById('user_email');
        if (emailInput) {
            emailInput.addEventListener('input', (e) => {
                e.target.value = e.target.value.toLowerCase().replace(/[^a-z0-9@._\-+]/g, '');
                this.showEmailSuggestions(e.target);
            });
            emailInput.addEventListener('blur', () => {
                setTimeout(() => { const s = document.getElementById('email-suggestions'); if (s) s.classList.add('hidden'); }, 200);
            });
        }

        // Fechar modais clicando fora
        if (this.paymentModal) {
            this.paymentModal.addEventListener('click', (e) => {
                if (e.target === this.paymentModal) {
                    this.hidePaymentModal();
                }
            });
        }

        if (this.registrationModal) {
            this.registrationModal.addEventListener('click', (e) => {
                if (e.target === this.registrationModal) {
                    this.hideRegistrationModal();
                }
            });
        }
    }

    /**
     * Detecta o MAC address do dispositivo
     */
    async detectDevice() {
        try {
            // 🎯 PRIORIDADE: MAC da URL (MikroTik) ou injetado pelo servidor
            const urlParams = new URLSearchParams(window.location.search);
            const macFromUrl = urlParams.get('mac') || urlParams.get('mikrotik_mac') || urlParams.get('client_mac')
                || (window.PORTAL_MAC || '');
            const ipFromUrl = urlParams.get('ip') || urlParams.get('client_ip')
                || (window.PORTAL_IP || '');
            
            if (macFromUrl && this.isValidMacAddress(macFromUrl)) {
                this.deviceMac = macFromUrl.toUpperCase();
                console.log('🎯 MAC capturado da URL:', this.deviceMac);
                if (this.isRandomizedMac(this.deviceMac)) {
                    console.log('ℹ️ MAC é randomizado (normal em dispositivos modernos)');
                }
                if (ipFromUrl) {
                    this.deviceIp = ipFromUrl;
                    console.log('🌐 IP do dispositivo capturado da URL:', this.deviceIp);
                }
                return;
            }

            if (ipFromUrl) {
                this.deviceIp = ipFromUrl;
                console.log('🌐 IP do dispositivo capturado da URL:', this.deviceIp);
            }

            // Se não tem MAC real na URL, tentar aguardar MAC real
            await this.waitForRealMac();
            
        } catch (error) {
            console.error('❌ Erro ao detectar dispositivo:', error);
            this.deviceMac = '';
        }
    }

    /** Contexto MikroTik (MAC/IP) para enviar ao backend */
    getPortalContextPayload() {
        const urlParams = new URLSearchParams(window.location.search);
        return {
            mac_address: this.deviceMac
                || urlParams.get('mac') || urlParams.get('mikrotik_mac') || urlParams.get('client_mac')
                || window.PORTAL_MAC || null,
            ip_address: this.deviceIp
                || urlParams.get('ip') || urlParams.get('client_ip')
                || window.PORTAL_IP || null,
        };
    }

    async fetchWithTimeout(url, options = {}, timeoutMs = 35000) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), timeoutMs);
        try {
            return await fetch(url, { ...options, signal: controller.signal });
        } finally {
            clearTimeout(timer);
        }
    }

    /**
     * Aguarda MAC real ser detectado (máximo 30 segundos)
     */
    async waitForRealMac() {
        console.log('🔍 Aguardando MAC real...');
        const maxAttempts = 4;

        for (let i = 0; i < maxAttempts; i++) {
            this.setLoadingMessage(
                'Identificando seu aparelho...',
                `Aguarde ${i + 1}/${maxAttempts} — o WiFi do ônibus precisa reconhecer o celular`
            );
            try {
                const response = await this.fetchWithTimeout('/api/detect-device', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.getCSRFToken()
                    },
                    body: JSON.stringify(this.getPortalContextPayload())
                }, 12000);

                const data = await response.json();
                const mac = data.mac_address || '';
                const detectedIp = data.client_ip || data.ip_address;
                
                if (mac && this.isValidMacAddress(mac)) {
                    this.deviceMac = mac.toUpperCase();
                    console.log('✅ MAC detectado:', this.deviceMac);
                    if (detectedIp) {
                        this.deviceIp = detectedIp;
                    }
                    return;
                }
                
                console.log(`⏳ Tentativa ${i + 1}/${maxAttempts} — aguardando MikroTik reportar MAC...`);
                await new Promise(resolve => setTimeout(resolve, 3000));
                
            } catch (error) {
                console.error('Erro na tentativa', i + 1, ':', error);
                await new Promise(resolve => setTimeout(resolve, 2000));
            }
        }
        
        try {
            const response = await this.fetchWithTimeout('/api/detect-device', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCSRFToken()
                },
                body: JSON.stringify(this.getPortalContextPayload())
            }, 12000);
            const data = await response.json();
            if (data.mac_address && this.isValidMacAddress(data.mac_address)) {
                this.deviceMac = data.mac_address.toUpperCase();
                if (data.client_ip || data.ip_address) {
                    this.deviceIp = data.client_ip || data.ip_address;
                }
                return;
            }
        } catch (e) {
            console.error('Falha na tentativa final:', e);
        }

        this.deviceMac = '';
        console.warn('⚠️ Não foi possível confirmar MAC — usuário deve estar no WiFi sem 4G');
    }

    /**
     * Valida formato do MAC address
     */
    isValidMacAddress(mac) {
        const macRegex = /^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/;
        return macRegex.test(mac);
    }

    /**
     * Verifica se MAC é randomizado (localmente administrado).
     * MACs randomizados são perfeitamente válidos - dispositivos modernos
     * (iOS 14+, Android 10+) usam MACs randomizados por padrão.
     * O MAC randomizado é consistente por rede (mesmo MAC para mesmo SSID).
     */
    isRandomizedMac(mac) {
        if (!mac || mac.length < 2) return false;
        const firstByte = parseInt(mac.substring(0, 2), 16);
        return (firstByte & 0x02) !== 0;
    }

    /**
     * Gera MAC fictício para desenvolvimento
     */
    generateMockMac() {
        const hex = '0123456789ABCDEF';
        let mac = '';
        for (let i = 0; i < 6; i++) {
            if (i > 0) mac += ':';
            mac += hex[Math.floor(Math.random() * 16)];
            mac += hex[Math.floor(Math.random() * 16)];
        }
        return mac;
    }

    /**
     * Verifica status da conexão
     */
    async checkConnectionStatus() {
        if (!this.deviceMac) return;

        try {
            const status = await this.getDeviceStatus(this.deviceMac);
            this.updateConnectionUI(status);

            if (status.connected) {
                if (status.ip_address && status.ip_address !== this.deviceIp) {
                    this.deviceIp = status.ip_address;
                    console.log('🌐 IP atualizado pelo status do dispositivo:', this.deviceIp);
                }
                this.showManageButton();
                this.startConnectionMonitoring();
            }
        } catch (error) {
            console.error('Erro ao verificar status:', error);
        }
    }

    /**
     * Obtém status do dispositivo via API MikroTik
     */
    async getDeviceStatus(macAddress) {
        try {
            const response = await fetch(`/api/mikrotik/status/${macAddress}`, {
                headers: {
                    'X-CSRF-TOKEN': this.getCSRFToken()
                }
            });

            if (!response.ok) {
                throw new Error('Erro ao obter status do dispositivo');
            }

            return await response.json();
        } catch (error) {
            // Retornar status mock para desenvolvimento
            return {
                connected: false,
                mac_address: macAddress,
                ip_address: null,
                expires_at: null,
                data_used: 0,
                status: 'offline'
            };
        }
    }

    /**
     * Libera acesso para o dispositivo
     */
    async allowDevice(macAddress) {
        try {
            const response = await fetch('/api/mikrotik/allow', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCSRFToken()
                },
                body: JSON.stringify({ mac_address: macAddress })
            });

            const result = await response.json();
            return result.success;
        } catch (error) {
            console.error('Erro ao liberar dispositivo:', error);
            // Simular sucesso para desenvolvimento
            return true;
        }
    }

    /**
     * Bloqueia dispositivo
     */
    async blockDevice(macAddress) {
        try {
            const response = await fetch('/api/mikrotik/block', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCSRFToken()
                },
                body: JSON.stringify({ mac_address: macAddress })
            });

            const result = await response.json();
            return result.success;
        } catch (error) {
            console.error('Erro ao bloquear dispositivo:', error);
            return false;
        }
    }

    /**
     * Obtém dados de uso
     */
    async getUsageData(macAddress) {
        try {
            const response = await fetch(`/api/mikrotik/usage/${macAddress}`, {
                headers: {
                    'X-CSRF-TOKEN': this.getCSRFToken()
                }
            });

            if (!response.ok) {
                throw new Error('Erro ao obter dados de uso');
            }

            return await response.json();
        } catch (error) {
            // Retornar dados mock
            return {
                data_used: 0,
                session_duration: 0,
                download_speed: 0,
                upload_speed: 0
            };
        }
    }

    /**
     * Mostra modal de registro
     */
    showRegistrationModal() {
        if (this.registrationModal) {
            this.resetRegistrationForm();
            this.registrationModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    }

    /**
     * Reseta o formulário de registro (SIMPLIFICADO)
     */
    resetRegistrationForm() {
        this.currentUserId = null;
        
        const phoneInput = document.getElementById('user_phone');
        const submitBtn = document.getElementById('registration-submit-btn');
        const errorDiv = document.getElementById('registration-errors');

        if (phoneInput) phoneInput.value = '';
        
        if (submitBtn) {
            submitBtn.innerHTML = '📱 GERAR QR CODE PIX';
            submitBtn.disabled = false;
        }

        if (errorDiv) {
            errorDiv.classList.add('hidden');
            errorDiv.className = 'hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg text-sm';
        }
    }

    /**
     * Esconde modal de registro
     */
    hideRegistrationModal() {
        if (this.registrationModal) {
            this.registrationModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    }

    /**
     * Mostra modal de pagamento
     */
    showPaymentModal() {
        if (this.paymentModal) {
            this.paymentModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    }

    /**
     * Esconde modal de pagamento
     */
    hidePaymentModal() {
        if (this.paymentModal) {
            this.paymentModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    }

    /**
     * Processa submissão do formulário de registro (SIMPLIFICADO - apenas telefone)
     * 🚀 OTIMIZADO: Mostra loading imediato e faz registro + QR Code em paralelo
     */
    async handleRegistrationSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const formData = new FormData(form);
        const phone = formData.get('phone').replace(/\D/g, '');
        const email = '';

        // Validar telefone brasileiro (10 ou 11 dígitos)
        if (phone.length < 10 || phone.length > 11) {
            this.showRegistrationError('Por favor, insira um telefone válido com DDD (10 ou 11 dígitos).');
            return;
        }

        // 🚀 MOSTRAR LOADING IMEDIATAMENTE
        const submitBtn = document.getElementById('registration-submit-btn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="animate-pulse">⏳ GERANDO QR CODE...</span>';
        submitBtn.disabled = true;
        
        // Esconder modal e mostrar loading global
        this.hideRegistrationModal();
        this.showLoading();

        try {
            // 🚀 VERIFICAR MAC EM PARALELO (não bloqueia)
            if (!this.deviceMac || this.deviceMac === 'DETECTING...') {
                await this.ensureRealIdentifiers();
            }

            // 🛡️ BLOQUEAR REGISTRO SEM MAC VÁLIDO (evita pagamento sem liberação)
            if (!this.deviceMac || !this.isValidMacAddress(this.deviceMac)) {
                this.hideLoading();
                const warning = document.getElementById('no-wifi-warning');
                if (warning) {
                    warning.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                    window._noWifiBlocked = true;
                } else {
                    this.showRegistrationModal();
                    this.showRegistrationError('Não foi possível identificar seu dispositivo. Desative os dados móveis, conecte ao WiFi "TocantinsTransporteWiFi" e tente novamente.');
                }
                return;
            }

            const data = {
                phone: phone,
                email: email,
                user_id: this.currentUserId,
                mac_address: this.deviceMac,
                ip_address: this.deviceIp
            };

            console.log('📤 ENVIANDO PARA BACKEND:', { phone, mac: this.deviceMac, ip: this.deviceIp });

            // 🚀 FAZER REGISTRO
            const response = await fetch('/api/register-for-payment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCSRFToken()
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                this.currentUserId = result.user_id;
                
                // 🔧 FIX: Se o usuário já tem sessão ativa (pagou e ainda tem tempo),
                // não pedir pagamento de novo - apenas liberar o novo MAC no Mikrotik
                if (result.already_active) {
                    console.log('✅ Usuário já tem acesso ativo! Liberando novo MAC...');
                    this.hideLoading();
                    this.showSuccessMessage('✅ Você já tem acesso ativo! Reconectando...');
                    await this.allowDevice(this.deviceMac);
                    
                    // Aguardar sync do Mikrotik e verificar conexão
                    setTimeout(() => {
                        this.checkConnectionStatus();
                    }, 5000);
                    return;
                }
                
                // 🚀 GERAR QR CODE IMEDIATAMENTE (já está com loading)
                console.log('✅ Cadastro OK, gerando QR Code PIX...');
                await this.processPixPaymentFast();
            } else {
                this.hideLoading();
                this.showRegistrationModal();
                if (result.errors) {
                    const errorMessages = Object.values(result.errors).flat();
                    this.showRegistrationError(errorMessages.join('<br>'));
                } else {
                    this.showRegistrationError(result.message || 'Erro no cadastro.');
                }
            }
        } catch (error) {
            console.error('Erro no registro:', error);
            this.hideLoading();
            this.showRegistrationModal();
            this.showRegistrationError('Erro de conexão. Tente novamente.');
        } finally {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    }
    
    /**
     * 🚀 Versão otimizada do processPixPayment (sem validações redundantes)
     */
    async processPixPaymentFast() {
        try {
            this.setLoadingMessage('Identificando aparelho...', 'Não feche esta tela');
            const identifiersOk = await this.ensureRealIdentifiers();
            if (!identifiersOk) {
                return;
            }

            this.setLoadingMessage('Gerando QR Code PIX...', 'Isso pode levar alguns segundos');
            const response = await this.fetchWithTimeout('/api/payment/pix/generate-qr', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCSRFToken()
                },
                body: JSON.stringify({
                    amount: window.WIFI_PRICE || 5.99,
                    mac_address: this.deviceMac,
                    user_id: this.currentUserId,
                    ip_address: this.deviceIp,
                    plan_duration: window.WIFI_SELECTED_PLAN?.duration || window.SESSION_DURATION || 12,
                    plan_name: window.WIFI_SELECTED_PLAN?.name || 'Viagem completa',
                    plan_suffix: window.WIFI_SELECTED_PLAN?.suffix || '/ viagem'
                })
            }, 40000);

            if (response.status === 422) {
                this.showNoWifiWarning();
                return;
            }

            const result = await response.json();

            if (result.success && result.qr_code) {
                this.hideLoading();
                this.showPixQRCode(result);
                console.log('💳 QR Code gerado:', { payment_id: result.payment_id, gateway: result.gateway });
            } else {
                this.hideLoading();
                this.showErrorMessage(result.message || 'Erro ao gerar QR Code PIX.');
            }
        } catch (error) {
            console.error('Erro no pagamento PIX:', error);
            this.hideLoading();
            if (error.name === 'AbortError') {
                this.showErrorMessage('Demorou demais para gerar o PIX. Desligue o 4G, confira o WiFi e tente novamente.');
            } else {
                this.showErrorMessage('Erro de conexão. Verifique sua internet.');
            }
        }
    }

    /**
     * Verifica se usuário já existe por email ou telefone
     */
    async checkExistingUser(field, value) {
        if (!value || value.length < 3) return;

        // Limpar valor dependendo do campo
        let cleanValue = value;
        if (field === 'phone') {
            cleanValue = value.replace(/\D/g, '');
            if (cleanValue.length < 10) return;
        }

        if (field === 'email') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(cleanValue)) return;
        }

        try {
            const payload = {};
            payload[field] = cleanValue;

            const response = await fetch('/api/check-user', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCSRFToken()
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (result.exists && result.user) {
                this.fillUserData(result.user);
                this.showUserFoundMessage(result.user.name);
            }
        } catch (error) {
            console.error('Erro ao verificar usuário:', error);
        }
    }

    /**
     * Preenche os dados do usuário no formulário (SIMPLIFICADO)
     */
    fillUserData(userData) {
        this.currentUserId = userData.id;

        const phoneInput = document.getElementById('user_phone');

        if (phoneInput && userData.phone) {
            // Aplicar formatação ao telefone
            const formattedPhone = this.formatPhoneNumber(userData.phone);
            phoneInput.value = formattedPhone;
        }

        // ✅ RECUPERAR MAC E IP DO BANCO (para usuários expirados que tentam reconectar)
        if (userData.mac_address && !this.deviceMac) {
            this.deviceMac = userData.mac_address;
            console.log('✅ MAC recuperado do banco:', this.deviceMac);
        }

        if (userData.ip_address && !this.deviceIp) {
            this.deviceIp = userData.ip_address;
            console.log('✅ IP recuperado do banco:', this.deviceIp);
        }

        if (!this.hasRealIdentifiers()) {
            this.ensureRealIdentifiers();
        }
    }

    /**
     * Mostra mensagem de usuário encontrado
     */
    showUserFoundMessage(name) {
        const errorDiv = document.getElementById('registration-errors');
        if (errorDiv) {
            errorDiv.innerHTML = `👋 Olá ${name}! Seus dados foram preenchidos automaticamente. Você pode editar se necessário.`;
            errorDiv.className = 'bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg text-sm';
            errorDiv.classList.remove('hidden');
            
            // Esconder após 5 segundos
            setTimeout(() => {
            errorDiv.classList.add('hidden');
            errorDiv.className = 'hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg text-sm';
            }, 5000);
        }
    }

    /**
     * Formata número de telefone brasileiro
     */
    formatPhoneNumber(phone) {
        const cleanPhone = phone.replace(/\D/g, '');
        
        if (cleanPhone.length === 11) {
            return `(${cleanPhone.substring(0, 2)}) ${cleanPhone.substring(2, 3)} ${cleanPhone.substring(3, 7)}-${cleanPhone.substring(7)}`;
        } else if (cleanPhone.length === 10) {
            return `(${cleanPhone.substring(0, 2)}) ${cleanPhone.substring(2, 6)}-${cleanPhone.substring(6)}`;
        }
        
        return phone;
    }

    /**
     * Mostra erro no formulário de registro
     */
    showRegistrationError(message) {
        const errorDiv = document.getElementById('registration-errors');
        if (errorDiv) {
            errorDiv.innerHTML = message;
            errorDiv.classList.remove('hidden');
            
            // Esconder após 5 segundos
            setTimeout(() => {
                errorDiv.classList.add('hidden');
            }, 5000);
        }
    }

    /**
     * Verifica se usuário existe e decide se mostra cadastro ou pagamento
     */
    async handleConnectClick() {
        // 🛡️ BLOQUEAR se não está no WiFi (sem MAC/IP)
        if (window._noWifiBlocked) {
            const warning = document.getElementById('no-wifi-warning');
            if (warning) warning.classList.remove('hidden');
            return;
        }

        this.showLoading();

        try {
            // Verificar se já temos o MAC
            if (!this.deviceMac) {
                await this.ensureRealIdentifiers();
            }

            if (!this.deviceMac || !this.isValidMacAddress(this.deviceMac)) {
                this.hideLoading();
                // Mostrar overlay de "conecte ao WiFi" em vez de mensagem genérica
                const warning = document.getElementById('no-wifi-warning');
                if (warning) {
                    warning.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                    window._noWifiBlocked = true;
                } else {
                    this.showErrorMessage('Não foi possível identificar seu dispositivo. Desative os dados móveis e conecte ao WiFi "TocantinsTransporteWiFi".');
                }
                return;
            }

            // Verificar se usuário já existe
            const response = await fetch(`/api/user/check-mac/${this.deviceMac}`);
            const data = await response.json();

            this.hideLoading();

            if (data.exists && data.user_id) {
                // Usuário já existe
                this.currentUserId = data.user_id;
                
                // 🔧 FIX: Se já tem sessão ativa, não pedir pagamento
                if (data.already_active) {
                    console.log('✅ Usuário já tem acesso ativo! MAC já está liberado.');
                    this.showSuccessMessage('✅ Você já tem acesso ativo! Conectando...');
                    await this.allowDevice(this.deviceMac);
                    setTimeout(() => {
                        this.checkConnectionStatus();
                    }, 5000);
                    return;
                }
                
                console.log('✅ Usuário já cadastrado, gerando QR Code PIX direto...');
                this.processPixPayment();
            } else {
                // Usuário novo - mostrar cadastro simplificado (apenas telefone)
                console.log('📝 Novo usuário, mostrando cadastro simplificado');
                this.showRegistrationModal();
            }

        } catch (error) {
            this.hideLoading();
            console.error('Erro ao verificar usuário:', error);
            // Em caso de erro, mostrar cadastro por segurança
            this.showRegistrationModal();
        }
    }

    /**
     * Processa pagamento PIX
     */
    async processPixPayment() {
        this.showLoading();
        this.hidePaymentModal();

        try {
            this.setLoadingMessage('Identificando aparelho...', 'Não feche esta tela');
            const identifiersOk = await this.ensureRealIdentifiers();
            if (!identifiersOk) {
                return;
            }

            if (!this.currentUserId) {
                this.showErrorMessage('Erro: Dados do usuário não encontrados. Faça o registro novamente.');
                return;
            }

            this.setLoadingMessage('Gerando QR Code PIX...', 'Isso pode levar alguns segundos');
            const response = await this.fetchWithTimeout('/api/payment/pix/generate-qr', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCSRFToken()
                },
                body: JSON.stringify({
                    amount: window.WIFI_PRICE || 5.99,
                    mac_address: this.deviceMac,
                    user_id: this.currentUserId,
                    ip_address: this.deviceIp,
                    plan_duration: window.WIFI_SELECTED_PLAN?.duration || window.SESSION_DURATION || 12,
                    plan_name: window.WIFI_SELECTED_PLAN?.name || 'Viagem completa',
                    plan_suffix: window.WIFI_SELECTED_PLAN?.suffix || '/ viagem'
                })
            }, 40000);

            if (response.status === 422) {
                this.showNoWifiWarning();
                return;
            }

            const result = await response.json();

            if (result.success && result.qr_code) {
                this.hideLoading();
                this.showPixQRCode(result);
                
                console.log('💳 Pagamento PIX criado:', {
                    payment_id: result.payment_id,
                    mac_address: this.deviceMac,
                    user_id: this.currentUserId,
                    gateway: result.gateway
                });
            } else {
                this.showErrorMessage(result.message || 'Erro ao gerar QR Code PIX.');
            }
        } catch (error) {
            console.error('Erro no pagamento PIX:', error);
            if (error.name === 'AbortError') {
                this.showErrorMessage('Demorou demais para gerar o PIX. Desligue o 4G, confira o WiFi e tente novamente.');
            } else {
                this.showErrorMessage('Erro de conexão. Verifique sua internet.');
            }
        } finally {
            this.hideLoading();
        }
    }

    formatCountdown(seconds) {
        const mins = Math.floor(seconds / 60).toString().padStart(2, '0');
        const secs = (seconds % 60).toString().padStart(2, '0');
        return `${mins}:${secs}`;
    }

    updatePixTimerDisplay(message) {
        const timerText = document.getElementById('pix-timer-text');
        if (timerText && message) {
            timerText.textContent = message;
        } else if (timerText) {
            timerText.textContent = this.formatCountdown(this.pixCountdownSeconds);
        }
    }

    updatePixStatusHint(message) {
        const statusHint = document.getElementById('pix-status-hint');
        if (statusHint) {
            statusHint.textContent = message;
        }
    }

    startPixCountdown() {
        this.stopPixCountdown();
        this.pixCountdownSeconds = 180; // 3 minutos
        this.pixPaymentConfirmed = false;
        this.updatePixTimerDisplay();
        this.updatePixStatusHint('Use este tempo para abrir o banco e finalizar o PIX.');

        this.pixTimerInterval = setInterval(() => {
            if (this.pixPaymentConfirmed) {
                this.stopPixCountdown();
                return;
            }

            this.pixCountdownSeconds -= 1;
            if (this.pixCountdownSeconds <= 0) {
                this.pixCountdownSeconds = 0;
                this.updatePixTimerDisplay();
                this.handlePixTimeout();
                return;
            }

            this.updatePixTimerDisplay();
        }, 1000);
    }

    stopPixCountdown() {
        if (this.pixTimerInterval) {
            clearInterval(this.pixTimerInterval);
            this.pixTimerInterval = null;
        }
    }

    handlePixTimeout() {
        this.stopPixCountdown();
        if (this.paymentCheckInterval) {
            clearInterval(this.paymentCheckInterval);
            this.paymentCheckInterval = null;
        }

        this.updatePixTimerDisplay('⏱️ Tempo esgotado');
        this.updatePixStatusHint('O QR Code expirou. Gere um novo pagamento.');

        const checkButton = document.getElementById('check-payment-status');
        if (checkButton) {
            checkButton.innerHTML = 'Tempo esgotado';
            checkButton.disabled = true;
            checkButton.classList.add('cursor-not-allowed');
        }

        this.showErrorMessage('Tempo para pagamento expirou. Gere um novo QR Code.');
    }

    /**
     * Exibe modal com QR Code PIX - Interface com 5 passos
     */
    showPixQRCode(data) {
        this._bypassRan = false; // reset por modal (cada pagamento libera de novo)
        const modal = document.createElement('div');
        modal.id = 'pix-modal';
        modal.className = 'fixed inset-0 bg-black bg-opacity-80 z-50 backdrop-blur-sm';
        
        // Detectar se é dispositivo móvel
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        
        modal.innerHTML = `
            <div class="flex items-center justify-center min-h-screen p-2 overflow-y-auto">
                <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl my-2 max-h-[98vh] flex flex-col overflow-hidden">
                    
                    <!-- Header -->
                    <div class="bg-gradient-to-r from-emerald-600 to-emerald-700 px-4 py-3 text-white">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <span class="text-sm font-bold">Pagamento PIX</span>
                            </div>
                            <div class="text-right">
                                <span class="text-xl font-extrabold">R$ ${data.qr_code.amount}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Timeline 3 Passos - Simplificada -->
                    <div class="bg-gray-50 px-4 py-2 border-b">
                        <div class="flex items-center justify-between">
                            <div class="flex flex-col items-center flex-1">
                                <div id="step-1" class="w-8 h-8 rounded-full bg-emerald-500 flex items-center justify-center text-white text-sm font-bold shadow-md">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                </div>
                                <span id="step-1-text" class="text-[11px] mt-1 text-emerald-700 font-bold">Copiar</span>
                            </div>
                            <div class="h-1 flex-1 bg-gray-200 rounded-full -mt-4 mx-1"><div id="line-1-2" class="h-full bg-gray-200 rounded-full transition-all duration-500" style="width:0%"></div></div>
                            <div class="flex flex-col items-center flex-1">
                                <div id="step-2" class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-400 text-sm font-bold">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                </div>
                                <span id="step-2-text" class="text-[11px] mt-1 text-gray-400 font-medium">Banco</span>
                            </div>
                            <div class="h-1 flex-1 bg-gray-200 rounded-full -mt-4 mx-1"><div id="line-2-3" class="h-full bg-gray-200 rounded-full transition-all duration-500" style="width:0%"></div></div>
                            <div class="flex flex-col items-center flex-1">
                                <div id="step-3" class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-400 text-sm font-bold">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.858 15.355-5.858 21.213 0"/></svg>
                                </div>
                                <span id="step-3-text" class="text-[11px] mt-1 text-gray-400 font-medium">Conectar</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Área de Conteúdo Dinâmico -->
                    <div id="dynamic-content" class="p-3 flex-1 overflow-y-auto">
                        
                        <!-- PASSO 1: QR Code + Copia e Cola (visível inicialmente) -->
                        <div id="step-1-content" class="hidden">

                            <!-- Status bypass / instruções (atualizado após API) -->
                            <div id="bypass-status-banner" class="bg-slate-50 border-2 border-slate-200 rounded-xl p-3 mb-3 shadow-sm">
                                <div class="flex items-start gap-2.5">
                                    <div id="bypass-banner-icon" class="w-10 h-10 bg-slate-400 rounded-full flex items-center justify-center flex-shrink-0 shadow">
                                        <div class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></div>
                                    </div>
                                    <div class="min-w-0">
                                        <p id="bypass-banner-title" class="text-slate-900 font-extrabold text-sm leading-tight">Preparando pagamento...</p>
                                        <p id="bypass-banner-text" class="text-slate-700 text-[11px] mt-1 leading-snug">
                                            <strong>1.</strong> Copie o código PIX abaixo<br>
                                            <strong>2.</strong> Abra o app do banco e <strong>cole</strong> o código<br>
                                            <strong>3.</strong> Confirme o pagamento — o WiFi libera sozinho
                                        </p>
                                    </div>
                                </div>
                            </div>

                            
                            ${!isMobile ? `
                            <!-- QR Code (apenas desktop) -->
                            <div class="text-center mb-2">
                                <div class="bg-white p-2 rounded-xl border-2 border-dashed border-emerald-300 inline-block shadow-sm">
                                    <img src="${data.qr_code.image_url}" alt="QR Code PIX" class="w-36 h-36 mx-auto">
                                </div>
                                <p class="text-gray-400 text-[10px] mt-1">No celular: copie o código. No computador: escaneie ou copie.</p>
                            </div>
                            
                            <div class="flex items-center gap-2 mb-2">
                                <div class="flex-1 h-px bg-gray-200"></div>
                                <span class="text-[10px] text-gray-400 font-medium">PASSO 1 — COPIE O CÓDIGO</span>
                                <div class="flex-1 h-px bg-gray-200"></div>
                            </div>
                            ` : `
                            <div class="flex items-center gap-2 mb-2">
                                <div class="flex-1 h-px bg-gray-200"></div>
                                <span class="text-[10px] text-blue-700 font-bold">PASSO 1 — COPIE O CÓDIGO</span>
                                <div class="flex-1 h-px bg-gray-200"></div>
                            </div>
                            `}
                            
                            <!-- Copia e Cola -->
                            <div class="bg-blue-50 rounded-xl p-2.5 mb-2 border border-blue-200">
                                <div class="bg-white border border-blue-200 rounded-lg p-2 mb-2 max-h-14 overflow-y-auto">
                                    <p class="text-[10px] text-gray-600 break-all font-mono leading-relaxed" id="pix-code">${data.qr_code.emv_string}</p>
                                </div>
                                <button id="copy-pix-code" class="w-full bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white font-bold py-2 rounded-lg text-xs transition-all flex items-center justify-center gap-1.5 shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    COPIAR CÓDIGO PIX
                                </button>
                            </div>
                            
                            <!-- Passo 2: abrir banco (aparece após copiar) -->
                            <div id="open-bank-area" class="hidden mb-2">
                                <div class="flex items-center gap-2 mb-1.5">
                                    <div class="flex-1 h-px bg-gray-200"></div>
                                    <span class="text-[10px] text-emerald-700 font-bold">PASSO 2 — ABRA O BANCO E COLE</span>
                                    <div class="flex-1 h-px bg-gray-200"></div>
                                </div>
                                <div id="after-copy-hint" class="hidden">
                                    <div class="bg-emerald-50 border border-emerald-300 rounded-lg p-2.5">
                                        <p class="text-emerald-800 font-bold text-xs">✅ Código copiado! Agora abra o <strong>app do banco</strong> e cole o PIX.</p>
                                        <p class="text-emerald-600 text-[10px] mt-1">Não abra o banco antes de copiar — o código precisa estar na área de transferência.</p>
                                    </div>
                                </div>
                                <div id="has-mobile-data" class="hidden">
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-2.5">
                                        <p class="text-blue-800 font-bold text-xs">📱 Use seus <strong>dados móveis (4G)</strong> para abrir o app do banco e colar o código.</p>
                                    </div>
                                </div>
                                <div id="no-mobile-data" class="hidden">
                                    <div class="bg-amber-50 border border-amber-300 rounded-lg p-2.5">
                                        <p class="text-amber-800 font-bold text-xs flex items-center gap-1.5">
                                            <span id="bypass-icon" class="inline-block"><div class="animate-spin w-3.5 h-3.5 border-2 border-amber-500 border-t-transparent rounded-full"></div></span>
                                            <span id="bypass-text">Liberando internet para você abrir o banco...</span>
                                        </p>
                                        <p class="text-amber-700 text-[10px] mt-1" id="bypass-subtext">Depois de copiar, abra o app do banco e cole o código PIX.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Timer + Já Paguei lado a lado no mobile, empilhado no desktop -->
                            <div class="flex items-center gap-2 mb-2">
                                <div class="flex items-center gap-1.5 bg-gray-50 rounded-lg px-2.5 py-1.5 border flex-shrink-0">
                                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span id="pix-timer-text" class="text-xs font-bold text-gray-700">03:00</span>
                                </div>
                                <button id="btn-paid" class="flex-1 bg-emerald-500 hover:bg-emerald-600 active:scale-[0.98] text-white font-bold py-2.5 rounded-lg text-xs transition-all shadow-md flex items-center justify-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    JÁ PAGUEI
                                </button>
                            </div>
                            
                            <!-- Indicador de verificação automática -->
                            <div id="auto-check-indicator" class="flex items-center justify-center gap-2 py-1.5">
                                <div class="flex gap-1">
                                    <div class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse"></div>
                                    <div class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse" style="animation-delay:0.2s"></div>
                                    <div class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse" style="animation-delay:0.4s"></div>
                                </div>
                                <span class="text-[10px] text-gray-400">Verificando pagamento automaticamente</span>
                            </div>
                        </div>
                        
                        <!-- PASSO 2: Verificando / Pagamento Confirmado -->
                        <div id="step-2-content" class="hidden">
                            
                            <!-- Sub-estado: Verificando -->
                            <div id="step-2-checking" class="text-center py-4">
                                <div class="mb-3">
                                    <div class="w-14 h-14 mx-auto mb-2 relative">
                                        <div class="absolute inset-0 rounded-full border-4 border-amber-200"></div>
                                        <div class="absolute inset-0 rounded-full border-4 border-amber-500 border-t-transparent animate-spin"></div>
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                        </div>
                                    </div>
                                    <p class="text-gray-800 font-bold text-sm">Verificando pagamento</p>
                                    <div class="flex items-center justify-center gap-1 mt-1">
                                        <span class="text-gray-500 text-xs">Consultando banco</span>
                                        <span class="inline-flex gap-0.5">
                                            <span class="w-1 h-1 bg-amber-400 rounded-full animate-bounce" style="animation-delay:0s"></span>
                                            <span class="w-1 h-1 bg-amber-400 rounded-full animate-bounce" style="animation-delay:0.15s"></span>
                                            <span class="w-1 h-1 bg-amber-400 rounded-full animate-bounce" style="animation-delay:0.3s"></span>
                                        </span>
                                    </div>
                                </div>
                                <div class="bg-amber-50 border border-amber-200 rounded-lg p-2.5">
                                    <div class="flex items-center justify-center gap-2">
                                        <div class="w-2 h-2 bg-amber-400 rounded-full animate-pulse"></div>
                                        <p class="text-amber-700 text-xs font-medium">Aguarde, estamos localizando seu pagamento...</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Sub-estado: Pago! (aparece quando confirmado) -->
                            <div id="step-2-paid" class="hidden text-center py-4">
                                <div class="mb-3">
                                    <div class="w-16 h-16 mx-auto mb-2 bg-emerald-100 rounded-full flex items-center justify-center animate-bounce-once">
                                        <svg class="w-8 h-8 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <p class="text-emerald-700 font-extrabold text-lg">Pagamento Confirmado!</p>
                                    <p class="text-emerald-600 text-xs mt-0.5">R$ ${data.qr_code.amount} recebido</p>
                                </div>
                                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-2.5">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <p class="text-emerald-700 text-[11px] font-bold">Verificado com sucesso</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- PASSO 3: Liberando + Conectado -->
                        <div id="step-3-content" class="hidden">
                            
                            <!-- Sub-estado: Liberando acesso -->
                            <div id="step-3-releasing" class="text-center py-3">
                                <div class="mb-3">
                                    <div class="w-14 h-14 mx-auto mb-2 relative">
                                        <div class="absolute inset-0 rounded-full border-4 border-blue-200"></div>
                                        <div class="absolute inset-0 rounded-full border-4 border-blue-500 border-t-transparent animate-spin"></div>
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.858 15.355-5.858 21.213 0"/></svg>
                                        </div>
                                    </div>
                                    <p class="text-gray-800 font-bold text-sm">Liberando seu acesso...</p>
                                    <p class="text-gray-500 text-[11px] mt-0.5">Configurando sua conexão WiFi</p>
                                </div>
                                
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-2">
                                    <div class="flex justify-between items-center mb-1.5">
                                        <span class="text-blue-700 text-[11px] font-bold">Progresso</span>
                                        <span class="text-blue-600 text-xs font-bold" id="release-countdown">01:00</span>
                                    </div>
                                    <div class="w-full bg-blue-100 rounded-full h-2 overflow-hidden">
                                        <div id="release-progress" class="bg-gradient-to-r from-blue-400 to-blue-600 h-2 rounded-full transition-all duration-1000" style="width:0%"></div>
                                    </div>
                                    <p class="text-blue-500 text-[10px] mt-1.5">Sincronizando com o roteador...</p>
                                </div>
                                
                                <div class="bg-red-50 border border-red-200 rounded-lg px-3 py-1.5">
                                    <p class="text-red-600 text-[10px] font-bold flex items-center justify-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                        Não feche esta tela!
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Sub-estado: Conectado! -->
                            <div id="step-3-connected" class="hidden text-center py-2">
                                <div class="bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-xl p-5 text-white shadow-lg">
                                    <div class="w-16 h-16 mx-auto mb-2 bg-white/20 rounded-full flex items-center justify-center">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.858 15.355-5.858 21.213 0"/></svg>
                                    </div>
                                    <p class="font-extrabold text-xl">Conectado!</p>
                                    <p class="text-emerald-100 text-xs mt-0.5">Aproveite a internet durante toda a viagem</p>
                                    
                                    <div class="bg-white/15 rounded-lg p-2.5 mt-3">
                                        <p class="text-emerald-100 text-[10px]">Acesso válido até</p>
                                        <p class="text-base font-bold mt-0.5" id="access-expires-at">${this.calculateExpirationTime()}</p>
                                    </div>
                                </div>
                                
                                <div class="mt-3 bg-gray-50 rounded-lg p-2 border">
                                    <div class="flex items-center justify-center gap-2">
                                        <div class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></div>
                                        <p class="text-gray-600 text-xs">Redirecionando em <span id="redirect-timer">3</span>s...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                    
                </div>
            </div>
        `;
        
        // Adicionar animação CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes bounce-once { 0%{transform:scale(0.3);opacity:0} 50%{transform:scale(1.1)} 70%{transform:scale(0.95)} 100%{transform:scale(1);opacity:1} }
            .animate-bounce-once { animation: bounce-once 0.6s ease-out; }
        `;
        document.head.appendChild(style);
        
        document.body.appendChild(modal);
        
        // Mostrar passo 1 (QR Code) direto
        document.getElementById('step-1-content').classList.remove('hidden');

        // Verificar bypass / limite e liberar internet se necessário (sem mostrar passo 2 antes de copiar)
        this.detectAndBypass(data.payment_id);
        
        // Event: Copiar código PIX
        document.getElementById('copy-pix-code')?.addEventListener('click', () => {
            this.copyPixCode(data.qr_code.emv_string);
            const btn = document.getElementById('copy-pix-code');
            if (btn) {
                btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> COPIADO!';
                btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                btn.classList.add('bg-emerald-500');

                const openBankArea = document.getElementById('open-bank-area');
                const afterCopyHint = document.getElementById('after-copy-hint');
                const hasMobile = document.getElementById('has-mobile-data');
                const noMobile = document.getElementById('no-mobile-data');
                if (openBankArea) openBankArea.classList.remove('hidden');
                if (afterCopyHint) afterCopyHint.classList.remove('hidden');
                if (this._bypassMode === 'mobile' && hasMobile) hasMobile.classList.remove('hidden');
                if (this._bypassMode === 'limit' && noMobile) noMobile.classList.remove('hidden');

                // 📧 Enviar email com código PIX (em background, não bloqueia)
                fetch('/api/payment/pix/send-email', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.getCSRFToken() },
                    body: JSON.stringify({ payment_id: data.payment_id })
                }).catch(() => {});
                
                setTimeout(() => {
                    btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg> COPIAR CÓDIGO PIX';
                    btn.classList.remove('bg-emerald-500');
                    btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                }, 2000);
            }
        });
        
        // Event: Botão "Já Paguei" - vai para passo 2 (verificando)
        document.getElementById('btn-paid')?.addEventListener('click', () => {
            this.goToStep2(data.payment_id);
        });
        
        // Salvar payment_id para uso posterior
        this.currentPaymentId = data.payment_id;
        
        // Iniciar timer e verificação automática imediatamente
        this.startPixCountdown();
        this.paymentCheckInterval = setInterval(() => {
            this.checkPaymentStatus(this.currentPaymentId);
        }, 5000);
    }
    
    /**
     * Vai para o Passo 2 - Verificando pagamento
     */
    goToStep2(paymentId) {
        // Atualizar timeline - Step 1 concluído
        document.getElementById('step-1').innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>';
        document.getElementById('step-1-text').textContent = 'Copiado';
        document.getElementById('line-1-2').style.width = '100%';
        document.getElementById('line-1-2').classList.remove('bg-gray-200');
        document.getElementById('line-1-2').classList.add('bg-emerald-400');
        
        // Step 2 ativo
        document.getElementById('step-2').classList.remove('bg-gray-200', 'text-gray-400');
        document.getElementById('step-2').classList.add('bg-amber-500', 'text-white', 'animate-pulse');
        document.getElementById('step-2').innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>';
        document.getElementById('step-2-text').classList.remove('text-gray-400');
        document.getElementById('step-2-text').classList.add('text-amber-600', 'font-bold');
        document.getElementById('step-2-text').textContent = 'Verificando';
        
        // Trocar conteúdo
        document.getElementById('step-1-content').classList.add('hidden');
        document.getElementById('step-2-content').classList.remove('hidden');
        
        // Verificar pagamento imediatamente
        this.checkPaymentStatus(paymentId || this.currentPaymentId);
    }
    
    /**
     * Mostra confirmação de pagamento (sub-estado do passo 2)
     * Depois vai para passo 3 automaticamente
     */
    showPaymentConfirmed() {
        this.pixPaymentConfirmed = true;
        this.stopPixCountdown();
        
        // Parar verificação automática
        if (this.paymentCheckInterval) {
            clearInterval(this.paymentCheckInterval);
            this.paymentCheckInterval = null;
        }
        
        // Garantir que step-1-content está escondido (caso auto-check detectou antes do clique)
        document.getElementById('step-1-content')?.classList.add('hidden');
        document.getElementById('step-2-content')?.classList.remove('hidden');
        
        // Atualizar timeline step-1 como concluído
        const step1 = document.getElementById('step-1');
        if (step1 && !step1.classList.contains('bg-emerald-500')) {
            step1.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>';
            document.getElementById('step-1-text').textContent = 'Copiado';
            document.getElementById('line-1-2').style.width = '100%';
            document.getElementById('line-1-2').classList.remove('bg-gray-200');
            document.getElementById('line-1-2').classList.add('bg-emerald-400');
        }
        
        // Step 2 - mostrar sub-estado "Pago!"
        document.getElementById('step-2-checking')?.classList.add('hidden');
        document.getElementById('step-2-paid')?.classList.remove('hidden');
        
        // Atualizar ícone do step 2
        document.getElementById('step-2').classList.remove('bg-amber-500', 'animate-pulse');
        document.getElementById('step-2').classList.add('bg-emerald-500');
        document.getElementById('step-2').innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>';
        document.getElementById('step-2-text').classList.remove('text-amber-600');
        document.getElementById('step-2-text').classList.add('text-emerald-600');
        document.getElementById('step-2-text').textContent = 'Pago!';
        
        // Após 2 segundos, ir para passo 3 (liberando)
        setTimeout(() => {
            this.goToStep3();
        }, 2000);
    }
    
    /**
     * Vai para o Passo 3 - Liberando acesso
     */
    goToStep3() {
        // Linha 2→3 completa
        document.getElementById('line-2-3').style.width = '100%';
        document.getElementById('line-2-3').classList.remove('bg-gray-200');
        document.getElementById('line-2-3').classList.add('bg-emerald-400');
        
        // Step 3 ativo
        document.getElementById('step-3').classList.remove('bg-gray-200', 'text-gray-400');
        document.getElementById('step-3').classList.add('bg-blue-500', 'text-white', 'animate-pulse');
        document.getElementById('step-3').innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.858 15.355-5.858 21.213 0"/></svg>';
        document.getElementById('step-3-text').classList.remove('text-gray-400');
        document.getElementById('step-3-text').classList.add('text-blue-600', 'font-bold');
        document.getElementById('step-3-text').textContent = 'Liberando';
        
        // Trocar conteúdo - esconder todos os passos anteriores
        document.getElementById('step-1-content')?.classList.add('hidden');
        document.getElementById('step-2-content')?.classList.add('hidden');
        document.getElementById('step-3-content')?.classList.remove('hidden');
        
        // Esconder botões antigos
        document.getElementById('btn-paid')?.classList.add('hidden');
        
        // Iniciar contador de 30 segundos (MikroTik sincroniza a cada 15s)
        this.startReleaseCountdown(30);
        
        // Liberar dispositivo no MikroTik em background
        this.allowDevice(this.deviceMac);

        // Após 35 segundos, mostrar ajuda de troubleshooting se ainda não conectou
        this.troubleshootTimer = setTimeout(() => {
            this.showTroubleshootingHelp();
        }, 35000);
    }
    
    /**
     * Vai para estado final - Conectado!
     */
    showConnected() {
        this.stopReleaseCountdown();
        
        // Step 3 concluído
        document.getElementById('step-3').classList.remove('bg-blue-500', 'animate-pulse');
        document.getElementById('step-3').classList.add('bg-emerald-500');
        document.getElementById('step-3').innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>';
        document.getElementById('step-3-text').classList.remove('text-blue-600');
        document.getElementById('step-3-text').classList.add('text-emerald-600');
        document.getElementById('step-3-text').textContent = 'Conectado!';
        
        // Trocar conteúdo
        document.getElementById('step-3-releasing')?.classList.add('hidden');
        document.getElementById('step-3-connected')?.classList.remove('hidden');
        
        // Countdown para redirect
        let redirectSeconds = 3;
        const redirectTimer = setInterval(() => {
            redirectSeconds--;
            const el = document.getElementById('redirect-timer');
            if (el) el.textContent = redirectSeconds;
            if (redirectSeconds <= 0) {
                clearInterval(redirectTimer);
                window.location.href = 'https://www.google.com';
            }
        }, 1000);
    }
    /**
     * Copia código PIX para a área de transferência
     */
    async copyPixCode(code) {
        try {
            await navigator.clipboard.writeText(code);
            this.showSuccessMessage('Código PIX copiado! Cole no seu app de pagamento.');
        } catch (error) {
            // Fallback para navegadores mais antigos
            const textArea = document.createElement('textarea');
            textArea.value = code;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            this.showSuccessMessage('Código PIX copiado!');
        }
    }

    /**
     * Atualiza o banner principal do modal PIX conforme status do bypass
     */
    updateBypassBanner(mode, options = {}) {
        const banner = document.getElementById('bypass-status-banner');
        const iconEl = document.getElementById('bypass-banner-icon');
        const titleEl = document.getElementById('bypass-banner-title');
        const textEl = document.getElementById('bypass-banner-text');
        if (!banner || !iconEl || !titleEl || !textEl) return;

        const copySteps = '<strong>1.</strong> Copie o código PIX abaixo<br><strong>2.</strong> Abra o app do banco e <strong>cole</strong> o código<br><strong>3.</strong> Confirme o pagamento — o WiFi libera sozinho';

        const styles = {
            checking: {
                banner: 'bg-slate-50 border-2 border-slate-200',
                icon: 'bg-slate-400',
                iconHtml: '<div class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></div>',
                title: 'text-slate-900',
                text: 'text-slate-700',
            },
            success: {
                banner: 'bg-emerald-50 border-2 border-emerald-400',
                icon: 'bg-emerald-500',
                iconHtml: '<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                title: 'text-emerald-900',
                text: 'text-emerald-800',
            },
            limit: {
                banner: 'bg-red-50 border-2 border-red-400',
                icon: 'bg-red-500',
                iconHtml: '<span class="text-white text-lg leading-none">!</span>',
                title: 'text-red-900',
                text: 'text-red-800',
            },
            mobile: {
                banner: 'bg-blue-50 border-2 border-blue-300',
                icon: 'bg-blue-500',
                iconHtml: '<span class="text-white text-sm">📱</span>',
                title: 'text-blue-900',
                text: 'text-blue-800',
            },
            blocked: {
                banner: 'bg-red-50 border-2 border-red-400',
                icon: 'bg-red-600',
                iconHtml: '<span class="text-white text-lg leading-none">🚫</span>',
                title: 'text-red-900',
                text: 'text-red-800',
            },
        };

        const s = styles[mode] || styles.checking;
        banner.className = `${s.banner} rounded-xl p-3 mb-3 shadow-sm`;
        iconEl.className = `w-10 h-10 ${s.icon} rounded-full flex items-center justify-center flex-shrink-0 shadow`;
        iconEl.innerHTML = s.iconHtml;
        titleEl.className = `${s.title} font-extrabold text-sm leading-tight`;
        textEl.className = `${s.text} text-[11px] mt-1 leading-snug`;

        if (mode === 'checking') {
            titleEl.textContent = 'Preparando pagamento...';
            textEl.innerHTML = copySteps;
        } else if (mode === 'success') {
            const remaining = options.remaining ?? 0;
            const extra = remaining > 0
                ? ` (${remaining} liberação${remaining > 1 ? 'ões' : ''} restante${remaining > 1 ? 's' : ''} nesta hora)`
                : ' (última liberação desta hora)';
            titleEl.textContent = 'Internet liberada por 3 minutos!';
            textEl.innerHTML = `Primeiro <strong>copie o código abaixo</strong>. Depois abra o app do banco e cole para pagar. O acesso completo libera automaticamente após o PIX.${extra}`;
        } else if (mode === 'limit') {
            titleEl.textContent = 'Limite de liberações usado';
            textEl.innerHTML = 'Você já usou as <strong>2 liberações por hora</strong>. Copie o código abaixo e pague com <strong>dados móveis (4G) ligados</strong>, ou aguarde 1 hora. Sem 4G não dá para abrir o app do banco.';
        } else if (mode === 'mobile') {
            titleEl.textContent = 'Copie primeiro, depois abra o banco';
            textEl.innerHTML = copySteps + '<br><br>📱 Você tem internet pelo <strong>4G</strong> — use para abrir o app do banco após copiar.';
        } else if (mode === 'blocked') {
            titleEl.textContent = 'Liberação temporária suspensa';
            textEl.innerHTML = options.message || 'Entre em contato com o suporte ou realize o pagamento com dados móveis.';
        }
    }

    /**
     * Detecta se tem internet (dados móveis) e ativa bypass automaticamente se não tem
     * Chamado na abertura do modal PIX
     */
    detectAndBypass(paymentId) {
        if (this._bypassRan) return;
        this._bypassRan = true;
        this._bypassMode = 'checking';

        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 3000);

        fetch('https://www.google.com/generate_204', {
            method: 'HEAD',
            mode: 'no-cors',
            cache: 'no-cache',
            signal: controller.signal
        })
        .then(() => {
            clearTimeout(timeout);
            this._bypassMode = 'mobile';
            this.updateBypassBanner('mobile');
        })
        .catch(() => {
            clearTimeout(timeout);
            this.activateBypassAuto(paymentId);
        });
    }

    /**
     * Ativa acesso temporario de 3 min automaticamente (sem botão extra)
     */
    async activateBypassAuto(paymentId) {
        this.updateBypassBanner('checking');
        try {
            const response = await fetch('/api/payment/pix/temp-bypass', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify({ payment_id: paymentId })
            });

            const result = await response.json();

            const icon = document.getElementById('bypass-icon');
            const text = document.getElementById('bypass-text');
            const subtext = document.getElementById('bypass-subtext');

            if (result.success) {
                this._bypassMode = 'success';
                if (result.already_bypassed || result.already_connected) {
                    this.updateBypassBanner('success', { remaining: result.bypasses_remaining ?? 1 });
                    if (icon) icon.innerHTML = '✅';
                    if (text) text.textContent = 'Internet já liberada — abra o banco e cole o código';
                    if (subtext) subtext.textContent = 'Copie o código acima se ainda não copiou.';
                } else {
                    const remaining = result.bypasses_remaining ?? 0;
                    this.updateBypassBanner('success', { remaining });
                    if (icon) icon.innerHTML = '✅';
                    if (text) text.textContent = 'Internet liberada! Agora abra o banco e cole o código';
                    if (subtext) subtext.textContent = remaining > 0
                        ? `Copie o código acima primeiro. (${remaining} liberação restante nesta hora)`
                        : 'Copie o código acima primeiro. (última liberação desta hora)';
                }

                const container = document.getElementById('no-mobile-data')?.querySelector('div');
                if (container) {
                    container.className = 'bg-emerald-50 border border-emerald-300 rounded-lg p-2.5';
                    container.querySelectorAll('p').forEach(p => {
                        p.classList.remove('text-amber-800', 'text-amber-700');
                        p.classList.add('text-emerald-800');
                    });
                }
            } else if (result.limit_reached) {
                this._bypassMode = 'limit';
                this.updateBypassBanner('limit');
                if (icon) icon.innerHTML = '🚫';
                if (text) text.textContent = 'Sem internet — limite de liberações atingido';
                if (subtext) subtext.textContent = 'Ligue o 4G, copie o código e pague pelo app do banco.';

                const container = document.getElementById('no-mobile-data')?.querySelector('div');
                if (container) {
                    container.className = 'bg-red-50 border border-red-300 rounded-lg p-2.5';
                    container.querySelectorAll('p').forEach(p => {
                        p.classList.remove('text-amber-800', 'text-amber-700');
                        p.classList.add('text-red-700');
                    });
                }
            } else if (result.blocked) {
                this._bypassMode = 'blocked';
                this.updateBypassBanner('blocked', { message: result.message });
                if (icon) icon.innerHTML = '🚫';
                if (text) text.textContent = result.message || 'Liberação suspensa';
                if (subtext) subtext.textContent = 'Use dados móveis para pagar.';
            } else {
                this.updateBypassBanner('mobile');
                if (icon) icon.innerHTML = '⚠️';
                if (text) text.textContent = result.message || 'Copie o código e use o 4G para pagar';
                if (subtext) subtext.textContent = 'Abra o app do banco somente depois de copiar.';
            }
        } catch (e) {
            console.error('Erro ao ativar bypass:', e);
            this.updateBypassBanner('mobile');
            const icon = document.getElementById('bypass-icon');
            const text = document.getElementById('bypass-text');
            if (icon) icon.innerHTML = '📱';
            if (text) text.textContent = 'Copie o código e use o 4G para abrir o banco';
        }
    }

    /**
     * Verifica status do pagamento - Usa o novo fluxo de 3 passos
     */
    async checkPaymentStatus(paymentId) {
        console.log('🔄 Verificando status do pagamento:', paymentId);
        
        // Contador de tentativas manuais (quando clicou "JÁ PAGUEI")
        if (!this._manualCheckCount) this._manualCheckCount = 0;
        this._manualCheckCount++;
        
        try {
            const response = await fetch(`/api/payment/pix/status?payment_id=${paymentId}`);
            const result = await response.json();
            
            console.log('📊 Resultado da verificação:', result);
            
            if (result.success && result.payment.status === 'completed') {
                console.log('✅ Pagamento confirmado!');
                this._manualCheckCount = 0;
                
                // Ir para confirmação (passo 2 sub-estado "pago") → depois passo 3
                this.showPaymentConfirmed();
            } else {
                console.log('⏱️ Pagamento ainda pendente (tentativa ' + this._manualCheckCount + ')');
                
                // Após 6 tentativas (30s) sem confirmação, mostrar "não encontrado"
                if (this._manualCheckCount >= 6) {
                    this._manualCheckCount = 0;
                    this.showPaymentNotFound();
                }
            }
        } catch (error) {
            console.error('❌ Erro ao verificar status do pagamento:', error);
            this._manualCheckCount++;
            if (this._manualCheckCount >= 6) {
                this._manualCheckCount = 0;
                this.showPaymentNotFound();
            }
        }
    }
    
    /**
     * Mostra mensagem de pagamento não encontrado e volta para o QR Code
     */
    showPaymentNotFound() {
        // Parar verificação automática
        if (this.paymentCheckInterval) {
            clearInterval(this.paymentCheckInterval);
            this.paymentCheckInterval = null;
        }
        
        const checkingEl = document.getElementById('step-2-checking');
        if (checkingEl) {
            checkingEl.innerHTML = `
                <div class="text-center py-4">
                    <div class="w-14 h-14 mx-auto mb-3 bg-red-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </div>
                    <p class="text-gray-800 font-bold text-sm mb-1">Pagamento não encontrado</p>
                    <p class="text-gray-500 text-xs mb-4">Não localizamos o pagamento. Copie o código PIX e pague pelo app do banco.</p>
                    <button id="btn-back-to-qr" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-2.5 rounded-lg text-xs transition-all shadow-md flex items-center justify-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        VOLTAR E COPIAR CÓDIGO
                    </button>
                </div>
            `;
            
            document.getElementById('btn-back-to-qr')?.addEventListener('click', () => {
                this.backToStep1();
            });
        }
    }
    
    /**
     * Volta para o Passo 1 (QR Code) para o usuário copiar e pagar
     */
    backToStep1() {
        // Resetar timeline visual
        document.getElementById('step-1').innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>';
        document.getElementById('step-1').classList.add('bg-emerald-500', 'text-white');
        document.getElementById('step-1-text').textContent = 'Copiar';
        document.getElementById('step-1-text').classList.add('text-emerald-700', 'font-bold');
        
        document.getElementById('line-1-2').style.width = '0%';
        document.getElementById('line-1-2').classList.remove('bg-emerald-400');
        document.getElementById('line-1-2').classList.add('bg-gray-200');
        
        document.getElementById('step-2').classList.remove('bg-amber-500', 'bg-emerald-500', 'text-white', 'animate-pulse');
        document.getElementById('step-2').classList.add('bg-gray-200', 'text-gray-400');
        document.getElementById('step-2').innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>';
        document.getElementById('step-2-text').textContent = 'Pagar';
        document.getElementById('step-2-text').classList.remove('text-amber-600', 'text-emerald-600', 'font-bold');
        document.getElementById('step-2-text').classList.add('text-gray-400');
        
        // Restaurar conteúdo do step-2-checking
        const checkingEl = document.getElementById('step-2-checking');
        if (checkingEl) {
            checkingEl.innerHTML = `
                <div class="mb-3">
                    <div class="w-14 h-14 mx-auto mb-2 relative">
                        <div class="absolute inset-0 rounded-full border-4 border-amber-200"></div>
                        <div class="absolute inset-0 rounded-full border-4 border-amber-500 border-t-transparent animate-spin"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                    </div>
                    <p class="text-gray-800 font-bold text-sm">Verificando pagamento</p>
                    <div class="flex items-center justify-center gap-1 mt-1">
                        <span class="text-gray-500 text-xs">Consultando banco</span>
                        <span class="inline-flex gap-0.5">
                            <span class="w-1 h-1 bg-amber-400 rounded-full animate-bounce" style="animation-delay:0s"></span>
                            <span class="w-1 h-1 bg-amber-400 rounded-full animate-bounce" style="animation-delay:0.15s"></span>
                            <span class="w-1 h-1 bg-amber-400 rounded-full animate-bounce" style="animation-delay:0.3s"></span>
                        </span>
                    </div>
                </div>
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-2.5">
                    <div class="flex items-center justify-center gap-2">
                        <div class="w-2 h-2 bg-amber-400 rounded-full animate-pulse"></div>
                        <p class="text-amber-700 text-xs font-medium">Aguarde, estamos localizando seu pagamento...</p>
                    </div>
                </div>
            `;
        }
        
        // Mostrar step 1, esconder step 2
        document.getElementById('step-1-content')?.classList.remove('hidden');
        document.getElementById('step-2-content')?.classList.add('hidden');
        document.getElementById('step-2-paid')?.classList.add('hidden');
        document.getElementById('step-2-checking')?.classList.remove('hidden');
        
        // Mostrar botão JÁ PAGUEI de novo
        document.getElementById('btn-paid')?.classList.remove('hidden');
        
        // Reiniciar verificação automática
        this._manualCheckCount = 0;
        this.startPixCountdown();
        this.paymentCheckInterval = setInterval(() => {
            this.checkPaymentStatus(this.currentPaymentId);
        }, 5000);
    }
    
    /**
     * Mostra painel de troubleshooting quando a liberação demora
     * Aparece após 35 segundos do passo 3 (liberando)
     */
    showTroubleshootingHelp() {
        const step3Content = document.getElementById('step-3-content');
        if (!step3Content) return;

        // Verificar se já mostrou
        if (document.getElementById('troubleshoot-panel')) return;

        const panel = document.createElement('div');
        panel.id = 'troubleshoot-panel';
        panel.className = 'mt-4 bg-amber-50 border border-amber-200 rounded-xl p-4 animate-fade-in';
        panel.innerHTML = `
            <p class="text-sm font-bold text-amber-800 mb-2">Ainda sem acesso?</p>
            <p class="text-xs text-amber-700 mb-3">Tente estas soluções:</p>
            <ol class="text-xs text-amber-700 space-y-1.5 mb-3">
                <li><strong>1.</strong> Desconecte e reconecte o WiFi</li>
                <li><strong>2.</strong> Desative e ative o WiFi do celular</li>
                <li><strong>3.</strong> <strong>iPhone:</strong> Ajustes &gt; WiFi &gt; (i) ao lado da rede &gt; Desative "Endereço Privado WiFi" e reconecte</li>
                <li><strong>4.</strong> Feche e abra o navegador</li>
            </ol>
            <div class="border-t border-amber-200 pt-3">
                <p class="text-xs text-amber-700 mb-2">Se nada funcionar, reative seu acesso:</p>
                <div class="flex gap-2">
                    <input type="tel" id="troubleshoot-phone" placeholder="(63) 99999-9999" 
                        class="flex-1 px-3 py-2 border border-amber-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-400"
                        maxlength="15">
                    <button type="button" id="troubleshoot-reactivate-btn"
                        class="px-3 py-2 bg-amber-500 text-white font-bold rounded-lg text-xs hover:bg-amber-600 transition-colors whitespace-nowrap">
                        Reativar
                    </button>
                </div>
                <div id="troubleshoot-result" class="hidden mt-2"></div>
            </div>
        `;
        step3Content.appendChild(panel);

        // Mask
        document.getElementById('troubleshoot-phone')?.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 11) v = v.substring(0, 11);
            if (v.length > 7) v = '(' + v.substring(0,2) + ') ' + v.substring(2,7) + '-' + v.substring(7);
            else if (v.length > 2) v = '(' + v.substring(0,2) + ') ' + v.substring(2);
            e.target.value = v;
        });

        // Reactivate handler
        document.getElementById('troubleshoot-reactivate-btn')?.addEventListener('click', async () => {
            const phoneInput = document.getElementById('troubleshoot-phone');
            const btn = document.getElementById('troubleshoot-reactivate-btn');
            const result = document.getElementById('troubleshoot-result');
            const phone = (phoneInput?.value || '').replace(/\D/g, '');

            if (phone.length < 10) {
                if (result) { result.classList.remove('hidden'); result.innerHTML = '<p class="text-xs text-red-600">Informe seu telefone com DDD</p>'; }
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="animate-pulse">...</span>';

            try {
                const resp = await fetch('/api/reativar-acesso', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.getCSRFToken(), 'Accept': 'application/json' },
                    body: JSON.stringify({ phone })
                });
                const data = await resp.json();
                if (result) {
                    result.classList.remove('hidden');
                    result.innerHTML = data.success
                        ? '<p class="text-xs text-emerald-700 font-semibold">' + data.message + '</p>'
                        : '<p class="text-xs text-red-600">' + (data.message || 'Erro ao reativar') + '</p>';
                }
            } catch { if (result) { result.classList.remove('hidden'); result.innerHTML = '<p class="text-xs text-red-600">Erro de conexão</p>'; } }
            finally { btn.disabled = false; btn.innerHTML = 'Reativar'; }
        });
    }

    /**
     * Inicia contador de liberação - Mostra "Conectado" quando terminar
     */
    startReleaseCountdown(seconds) {
        this.releaseCountdownSeconds = seconds;
        this.releaseCountdownInterval = setInterval(() => {
            this.releaseCountdownSeconds--;
            
            const countdownEl = document.getElementById('release-countdown');
            const progressEl = document.getElementById('release-progress');
            
            if (countdownEl) {
                const mins = Math.floor(this.releaseCountdownSeconds / 60).toString().padStart(2, '0');
                const secs = (this.releaseCountdownSeconds % 60).toString().padStart(2, '0');
                countdownEl.textContent = `${mins}:${secs}`;
            }
            
            if (progressEl) {
                const progress = ((seconds - this.releaseCountdownSeconds) / seconds) * 100;
                progressEl.style.width = `${progress}%`;
            }
            
            // Quando o contador chegar a 0, mostrar conectado
            if (this.releaseCountdownSeconds <= 0) {
                this.showConnected();
            }
        }, 1000);
    }
    
    /**
     * Para contador de liberação
     */
    stopReleaseCountdown() {
        if (this.releaseCountdownInterval) {
            clearInterval(this.releaseCountdownInterval);
            this.releaseCountdownInterval = null;
        }
    }

    /**
     * Fecha modal do PIX
     */
    closePixModal() {
        const modal = document.getElementById('pix-modal');
        if (modal) {
            if (this.paymentCheckInterval) {
                clearInterval(this.paymentCheckInterval);
                this.paymentCheckInterval = null;
            }
            if (this.troubleshootTimer) {
                clearTimeout(this.troubleshootTimer);
                this.troubleshootTimer = null;
            }
            this.stopReleaseCountdown();
            this.stopPixCountdown();
            this.pixPaymentConfirmed = false;
            this.pixCountdownSeconds = 0;
            modal.remove();
        }
    }

    /**
     * Processa pagamento com cartão
     */
    async processCardPayment() {
        this.showLoading();
        this.hidePaymentModal();

        try {
            const response = await fetch('/api/payment/card', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCSRFToken()
                },
                body: JSON.stringify({
                    amount: 0.05,
                    mac_address: this.deviceMac,
                    user_id: this.currentUserId
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccessMessage('✅ Pagamento aprovado! Conectando à Starlink...');
                const allowed = await this.allowDevice(this.deviceMac);
                
                if (allowed) {
                    setTimeout(() => {
                        this.showSuccessMessage('🛰️ Conectado à Starlink! Navegue à vontade com a melhor internet do Brasil...');
                        
                        // Redirecionar para o Google após 2 segundos
                        setTimeout(() => {
                            window.location.href = 'https://www.google.com';
                        }, 2000);
                    }, 2000);
                }
            } else {
                this.showErrorMessage(result.message || 'Erro no pagamento.');
            }
        } catch (error) {
            console.error('Erro no pagamento:', error);
            this.showErrorMessage('Erro de conexão. Verifique sua internet.');
        } finally {
            this.hideLoading();
        }
    }


    /**
     * Aplica voucher
     */
    async applyVoucher(inputId = 'voucher-code') {
        const voucherInput = document.getElementById(inputId);
        const code = voucherInput?.value.trim();

        if (!code) {
            this.showErrorMessage('Digite um código de voucher válido.');
            return;
        }

        this.showLoading();

        try {
            const identifiersOk = await this.ensureRealIdentifiers();
            if (!identifiersOk) {
                this.hideLoading();
                return;
            }

            const response = await fetch('/api/voucher/apply', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCSRFToken()
                },
                body: JSON.stringify({
                    code: code,
                    mac_address: this.deviceMac,
                    ip_address: this.deviceIp,
                    plan_duration: window.WIFI_SELECTED_PLAN?.duration || window.SESSION_DURATION || 12,
                    plan_name: window.WIFI_SELECTED_PLAN?.name || 'Viagem completa',
                    plan_suffix: window.WIFI_SELECTED_PLAN?.suffix || '/ viagem'
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccessMessage('✅ Voucher aplicado! Conectando à Starlink...');
                if (voucherInput) voucherInput.value = '';
                
                const allowed = await this.allowDevice(this.deviceMac);
                if (allowed) {
                    setTimeout(() => {
                        this.showSuccessMessage('🛰️ Conectado à Starlink! Navegue à vontade com a melhor internet do Brasil...');
                        
                        // Redirecionar para o Google após 2 segundos
                        setTimeout(() => {
                            window.location.href = 'https://www.google.com';
                        }, 2000);
                    }, 2000);
                }
            } else {
                this.showErrorMessage(result.message || 'Voucher inválido ou expirado.');
            }
        } catch (error) {
            console.error('Erro ao aplicar voucher:', error);
            this.showErrorMessage('Erro de conexão. Tente novamente.');
        } finally {
            this.hideLoading();
        }
    }

    /**
     * Atualiza interface com status da conexão
     */
    updateConnectionUI(status) {
        const statusElement = document.getElementById('connection-status');
        const statusText = document.getElementById('status-text');

        if (statusElement && statusText) {
            statusElement.classList.remove('hidden');
            
            if (status.connected) {
                statusText.textContent = 'Conectado';
                statusElement.className = 'bg-green-100 border border-green-200 rounded-xl p-4 mb-4';
            } else {
                statusText.textContent = 'Desconectado';
                statusElement.className = 'bg-red-100 border border-red-200 rounded-xl p-4 mb-4';
            }
        }
    }

    /**
     * Mostra botão de gerenciar conexão
     */
    showManageButton() {
        const manageBtn = document.getElementById('manage-connection');
        if (manageBtn) {
            manageBtn.classList.remove('hidden');
        }
    }

    /**
     * Inicia monitoramento da conexão
     */
    startConnectionMonitoring() {
        if (this.connectionCheckInterval) {
            clearInterval(this.connectionCheckInterval);
        }

        this.connectionCheckInterval = setInterval(() => {
            this.checkConnectionStatus();
        }, 30000); // Verifica a cada 30 segundos
    }

    /**
     * Mostra gerenciador de conexão
     */
    showConnectionManager() {
        alert('Funcionalidade de gerenciamento em desenvolvimento');
    }

    /**
     * Exibe loading
     */
    showLoading() {
        if (this.loadingOverlay) {
            this.loadingOverlay.classList.remove('hidden');
            this.setLoadingMessage('Processando...', 'Por favor, aguarde');
        }
    }

    /**
     * Atualiza textos do overlay de loading
     */
    setLoadingMessage(title, subtitle) {
        const titleEl = document.getElementById('loading-title');
        const subEl = document.getElementById('loading-subtitle');
        const hintEl = document.getElementById('loading-hint');
        if (titleEl && title) titleEl.textContent = title;
        if (subEl && subtitle) subEl.textContent = subtitle;
        if (hintEl) {
            const showHint = (title || '').toLowerCase().includes('gerando') || (title || '').toLowerCase().includes('identificando');
            hintEl.classList.toggle('hidden', !showHint);
        }
    }

    /**
     * Esconde loading
     */
    hideLoading() {
        if (this.loadingOverlay) {
            this.loadingOverlay.classList.add('hidden');
        }
    }

    /**
     * Exibe mensagem de sucesso
     */
    showSuccessMessage(message) {
        this.showToast(message, 'success');
    }

    /**
     * Exibe mensagem de erro
     */
    showErrorMessage(message) {
        this.showToast(message, 'error');
    }

    /**
     * Exibe mensagem informativa
     */
    showInfoMessage(message) {
        this.showToast(message, 'info');
    }

    /**
     * Exibe toast notification
     */
    showToast(message, type) {
        const toast = document.createElement('div');
        let bgColor = 'bg-red-500';
        if (type === 'success') bgColor = 'bg-green-500';
        if (type === 'info') bgColor = 'bg-blue-500';
        
        toast.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-medium animate-slide-up ${bgColor}`;
        toast.textContent = message;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 5000);
    }


    /**
     * Mostra sugestões de domínio de email
     */
    showEmailSuggestions(input) {
        const box = document.getElementById('email-suggestions');
        if (!box) return;
        const val = input.value;
        const atIndex = val.indexOf('@');
        if (atIndex < 1) { box.classList.add('hidden'); return; }
        const typed = val.substring(atIndex + 1);
        if (typed.includes('.') && typed.split('.').pop().length >= 2) { box.classList.add('hidden'); return; }
        const user = val.substring(0, atIndex);
        const domains = ['gmail.com','hotmail.com','outlook.com','yahoo.com.br','icloud.com','live.com','bol.com.br','uol.com.br'];
        const matches = domains.filter(d => d.startsWith(typed) && d !== typed);
        if (matches.length === 0) { box.classList.add('hidden'); return; }
        box.innerHTML = matches.slice(0, 4).map(d =>
            `<button type="button" class="w-full text-left px-4 py-2.5 text-sm hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-0" onclick="document.getElementById('user_email').value='${user}@${d}';document.getElementById('email-suggestions').classList.add('hidden')">
                <span class="text-gray-500">${user}@</span><span class="font-semibold text-gray-800">${d}</span>
            </button>`
        ).join('');
        box.classList.remove('hidden');
    }

    /**
     * Aplica máscara de telefone brasileiro (XX) X XXXX-XXXX
     */
    applyPhoneMask(e) {
        let value = e.target.value.replace(/\D/g, ''); // Remove tudo que não é dígito
        
        // Limita a 11 dígitos
        if (value.length > 11) {
            value = value.slice(0, 11);
        }
        
        // Aplica a máscara
        if (value.length <= 2) {
            value = value.replace(/(\d{0,2})/, '($1');
        } else if (value.length <= 3) {
            value = value.replace(/(\d{2})(\d{0,1})/, '($1) $2');
        } else if (value.length <= 7) {
            value = value.replace(/(\d{2})(\d{1})(\d{0,4})/, '($1) $2 $3');
        } else {
            value = value.replace(/(\d{2})(\d{1})(\d{4})(\d{0,4})/, '($1) $2 $3-$4');
        }
        
        e.target.value = value;
    }

    /**
     * Lida com teclas especiais no campo de telefone
     */
    handlePhoneKeydown(e) {
        // Permite: backspace, delete, tab, escape, enter
        if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
            // Permite: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
            (e.keyCode === 65 && e.ctrlKey === true) ||
            (e.keyCode === 67 && e.ctrlKey === true) ||
            (e.keyCode === 86 && e.ctrlKey === true) ||
            (e.keyCode === 88 && e.ctrlKey === true) ||
            // Permite: setas
            (e.keyCode >= 35 && e.keyCode <= 39)) {
            return;
        }
        
        // Bloqueia se não for número
        if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
            e.preventDefault();
        }
    }

    /**
     * Obtém CSRF token
     */
    getCSRFToken() {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        return token || '';
    }

    hasRealIdentifiers() {
        return (
            this.deviceMac &&
            this.deviceMac.length === 17 &&
            this.isValidMacAddress(this.deviceMac) &&
            this.deviceMac !== '00:00:00:00:00:00' &&
            this.deviceIp
        );
    }

    redirectToCaptivePortal() {
        const captiveUrl = 'http://10.5.50.1';
        const returnParam = encodeURIComponent(window.location.href);
        window.location.replace(`${captiveUrl}?return_url=${returnParam}`);
    }

    /**
     * Mostra o aviso "desligue o 4G / conecte ao WiFi" e bloqueia o fluxo.
     * Usado sempre que não conseguimos confirmar o MAC do hotspot — que é
     * exatamente o que acontece quando o cliente está com os dados móveis (4G)
     * ligados: o servidor não mapeia IP→MAC e o pagamento não libera o acesso.
     */
    showNoWifiWarning(message) {
        this.hideLoading();
        const warning = document.getElementById('no-wifi-warning');
        if (warning) {
            warning.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            window._noWifiBlocked = true;
        } else {
            this.showErrorMessage(message || 'Desligue os dados móveis (4G) e conecte ao WiFi "TocantinsTransporteWiFi" antes de pagar.');
        }
    }

    async ensureRealIdentifiers() {
        if (this.hasRealIdentifiers()) {
            return true;
        }

        try {
            await this.detectDevice();
        } catch (error) {
            console.warn('detecção falhou', error);
        }

        if (this.hasRealIdentifiers()) {
            return true;
        }

        // Mostrar overlay de "conecte ao WiFi" em vez de redirecionar
        this.hideLoading();
        const warning = document.getElementById('no-wifi-warning');
        if (warning) {
            warning.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            window._noWifiBlocked = true;
        } else {
            this.showErrorMessage('Não conseguimos identificar seu dispositivo. Desative os dados móveis e conecte ao WiFi "TocantinsTransporteWiFi".');
        }
        return false;
    }
}

// Inicializar quando DOM estiver carregado
document.addEventListener('DOMContentLoaded', () => {
    const portal = new WiFiPortal();
    window.wifiPortal = portal;
    
    // Adicionar event listeners para botões de pagamento
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-payment="pix"]')) {
            portal.processPixPayment();
        }
        if (e.target.closest('[data-payment="card"]')) {
            portal.processCardPayment();
        }
    });
});

// Exportar para uso global
window.WiFiPortal = WiFiPortal;
