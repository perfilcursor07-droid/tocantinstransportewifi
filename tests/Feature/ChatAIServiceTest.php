<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Payment;
use App\Models\User;
use App\Services\ChatAIService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChatAIServiceTest extends TestCase
{
    public function createApplication()
    {
        $app = require __DIR__ . '/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'logging.default' => 'null',
        ]);
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('status')->default('pending');
            $table->string('role')->default('user');
            $table->string('last_mikrotik_id')->nullable();
            $table->dateTime('connected_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->decimal('amount', 10, 2);
            $table->string('payment_type');
            $table->string('status');
            $table->json('payment_data')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_name');
            $table->string('visitor_phone');
            $table->string('visitor_email')->nullable();
            $table->string('visitor_ip')->nullable();
            $table->string('visitor_mac')->nullable();
            $table->string('session_id')->unique();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->dateTime('last_message_at')->nullable();
            $table->integer('unread_count')->default(0);
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('sender_type');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->text('message');
            $table->string('type')->default('text');
            $table->json('metadata')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    public function test_no_payment_is_explained_as_no_active_payment(): void
    {
        [$conversation] = $this->conversation('Selma Lima Oliveira', '63999990001');
        $this->visitorMessage($conversation, 'Nao estou conseguindo acesso a Internet');

        $reply = app(ChatAIService::class)->respond($conversation);

        $this->assertSame('text', $reply->type);
        $this->assertStringContainsString('não encontrei pagamento ativo', $reply->message);
        $this->assertStringContainsString('necessário fazer um novo pagamento', $reply->message);
        $this->assertStringNotContainsString('cadastro expirado', mb_strtolower($reply->message));
    }

    public function test_paid_claim_without_active_payment_requests_receipt_immediately(): void
    {
        [$conversation] = $this->conversation('João', '63999990002');
        $this->visitorMessage($conversation, 'Paguei a internet pelo 4g, mas não deu certo');

        $reply = app(ChatAIService::class)->respond($conversation);

        $this->assertSame('receipt_request', $reply->type);
        $this->assertStringContainsString('não aparece como ativo', $reply->message);
        $this->assertStringContainsString('comprovante', $reply->message);
        $this->assertStringNotContainsString('você já pagou', mb_strtolower($reply->message));
    }

    public function test_bank_without_internet_gets_two_stage_pix_instructions(): void
    {
        [$conversation] = $this->conversation('Kathleen', '63999990003');
        $this->visitorMessage($conversation, 'Quero colocar só que não tenho Internet pra abrir o banco');

        $reply = app(ChatAIService::class)->respond($conversation);

        $this->assertSame('text', $reply->type);
        $this->assertStringContainsString('copie o código PIX', $reply->message);
        $this->assertStringContainsString('ligue o 4G e pague no banco', $reply->message);
        $this->assertStringContainsString('sem esquecer a rede', mb_strtolower($reply->message));
    }

    public function test_device_answer_does_not_request_mac_without_valid_payment(): void
    {
        [$conversation] = $this->conversation('João', '63999990004');
        $this->assistantMessage($conversation, 'Você está usando iOS (iPhone) ou Android?');
        $this->visitorMessage($conversation, 'iOS');

        $reply = app(ChatAIService::class)->respond($conversation);

        $this->assertSame('text', $reply->type);
        $this->assertStringContainsString('não encontrei pagamento ativo', $reply->message);
    }

    public function test_device_answer_can_request_mac_when_payment_is_valid(): void
    {
        [$conversation, $user] = $this->conversation('João', '63999990005');
        Payment::create([
            'user_id' => $user->id,
            'amount' => 6.99,
            'payment_type' => 'pix',
            'status' => 'completed',
            'paid_at' => now(),
            'payment_data' => ['duration_hours' => 12],
        ]);
        $this->assistantMessage($conversation, 'Você está usando iOS (iPhone) ou Android?');
        $this->visitorMessage($conversation, 'iOS');

        $reply = app(ChatAIService::class)->respond($conversation);

        $this->assertSame('mac_request', $reply->type);
        $this->assertStringContainsString('MAC da rede deste iPhone', $reply->message);
    }

    private function conversation(string $name, string $phone): array
    {
        $user = User::create([
            'name' => $name,
            'phone' => $phone,
            'status' => 'pending',
            'expires_at' => now()->subHour(),
        ]);

        $conversation = ChatConversation::create([
            'visitor_name' => $name,
            'visitor_phone' => $phone,
            'session_id' => fake()->uuid(),
            'status' => 'active',
            'last_message_at' => now(),
        ]);

        return [$conversation, $user];
    }

    private function visitorMessage(ChatConversation $conversation, string $message): void
    {
        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'visitor',
            'message' => $message,
        ]);
    }

    private function assistantMessage(ChatConversation $conversation, string $message): void
    {
        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'admin',
            'message' => $message,
            'metadata' => ['ai' => true],
        ]);
    }
}
