// Sistema de detecção de MAC melhorado
// NOTA: MACs randomizados do dispositivo (iOS 14+, Android 10+) começam com 02:, 06:, 0A:...
// e SÃO válidos — são consistentes por rede.
// Porém, o backend só retorna MAC aqui quando ele foi confirmado pelo MikroTik
// (via report, URL, header ou ARP). Se o backend não tem certeza, retorna null
// e pede retry — assim nunca gravamos um MAC fictício no cadastro.
class MacDetector {
    constructor() {
        this.realMac = null;
        this.attempts = 0;
        this.maxAttempts = 8; // ~40s total
    }

    // Formato correto e não é MAC broadcast/zero
    isValidMac(mac) {
        if (!mac) return false;
        const macRegex = /^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/;
        if (!macRegex.test(mac)) return false;
        const upper = mac.toUpperCase();
        return upper !== '00:00:00:00:00:00' && upper !== 'FF:FF:FF:FF:FF:FF';
    }

    // Detectar MAC com retry
    async detectRealMac() {
        console.log('🔍 Tentando detectar MAC do dispositivo...');

        for (let i = 0; i < this.maxAttempts; i++) {
            try {
                const mac = await this.tryDetectMac();

                if (mac && this.isValidMac(mac)) {
                    console.log('✅ MAC confirmado:', mac);
                    this.realMac = mac.toUpperCase();
                    this.saveDetectedMac(this.realMac);
                    return this.realMac;
                }

                console.log(`⏳ Tentativa ${i + 1}/${this.maxAttempts} — MikroTik ainda não reportou o MAC, aguardando...`);
                await this.delay(5000);

            } catch (error) {
                console.error('Erro na detecção:', error);
                await this.delay(3000);
            }
        }

        console.warn('⚠️ Não foi possível confirmar MAC com o MikroTik após várias tentativas');
        return null;
    }

    // Tentar detectar MAC via múltiplos métodos
    async tryDetectMac() {
        const urlParams = new URLSearchParams(window.location.search);

        // Método 1: Via API detect-device (fonte de verdade — só retorna MAC confirmado)
        try {
            const response = await fetch('/api/detect-device', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    mac_address: urlParams.get('mac') || urlParams.get('mikrotik_mac') || window.PORTAL_MAC || null,
                    ip_address: urlParams.get('ip') || urlParams.get('client_ip') || window.PORTAL_IP || null,
                })
            });

            const data = await response.json();
            if (data.mac_address && this.isValidMac(data.mac_address)) {
                return data.mac_address;
            }
            // needs_retry=true significa "ainda não confirmado" — seguir tentando
        } catch (error) {
            console.log('Método 1 falhou:', error);
        }

        // Método 2: Via parâmetros URL (se vier do hotspot)
        const macFromUrl = urlParams.get('mac') || urlParams.get('mikrotik_mac') || window.PORTAL_MAC;
        if (macFromUrl && this.isValidMac(macFromUrl)) {
            return macFromUrl;
        }

        // Método 3: Via localStorage (cache de detecção anterior bem-sucedida)
        const cachedMac = localStorage.getItem('real_mac');
        if (cachedMac && this.isValidMac(cachedMac)) {
            return cachedMac;
        }

        return null;
    }

    // Aguardar com callback visual
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    // Salvar MAC detectado (só salva MACs confirmados pelo backend)
    saveDetectedMac(mac) {
        if (mac && this.isValidMac(mac)) {
            localStorage.setItem('real_mac', mac.toUpperCase());
            this.realMac = mac.toUpperCase();
            console.log('💾 MAC salvo:', this.realMac);
        }
    }

    // Obter MAC para pagamento — retorna null se não tivermos confirmação
    getMacForPayment() {
        return this.realMac || localStorage.getItem('real_mac') || null;
    }
}

// Instância global
window.macDetector = new MacDetector();

// Auto-detectar ao carregar página
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Iniciando detecção de MAC...');
    window.macDetector.detectRealMac();
});
