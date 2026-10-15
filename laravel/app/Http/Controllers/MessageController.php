<?php

namespace App\Http\Controllers;

use App\Models\UserAccount;
use App\Services\Messaging;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

/**
 * Messages, ported from messages.php. Who may message or open a conversation
 * with whom is decided by UserAccountPolicy (message, openConversation).
 */
class MessageController extends Controller
{
    private const TEXT_RULES = ['required', 'string', 'max:2000'];

    private const TEXT_MESSAGES = [
        'message_text.required' => 'Please enter a message.',
        'message_text.string' => 'Please enter a message.',
        'message_text.max' => 'Message is too long. Maximum 2000 characters.',
    ];

    public function __construct(private readonly Messaging $messaging) {}

    public function index(Request $request): View|RedirectResponse
    {
        // Old-style links (messages?with=ID) open the conversation route.
        $with = (int) $request->query('with', 0);
        if ($with > 0) {
            return redirect()->route('messages.show', $with);
        }

        $compose = $request->has('compose');
        $search = trim((string) $request->query('q', ''));

        return $this->render($request->user(), compose: $compose, search: $search);
    }

    public function show(Request $request, UserAccount $partner): View
    {
        return $this->render($request->user(), partner: $partner);
    }

    /**
     * "New message" form: the recipient comes from the form, so the policy runs as a validation rule.
     */
    public function store(Request $request): RedirectResponse
    {
        $me = $request->user();
        $recipient = null;

        $request->validate([
            'message_text' => self::TEXT_RULES,
            'recipient_id' => ['bail', 'required', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($me, &$recipient) {
                $recipient = UserAccount::find((int) $value);
                if (! $recipient) {
                    $fail('Recipient not found or inactive.');

                    return;
                }
                $response = Gate::forUser($me)->inspect('message', $recipient);
                if ($response->denied()) {
                    $fail($response->message());
                }
            }],
        ], self::TEXT_MESSAGES + [
            'recipient_id.required' => 'Recipient not found or inactive.',
            'recipient_id.integer' => 'Recipient not found or inactive.',
        ]);

        return $this->deliver($me, $recipient, $request->input('message_text'));
    }

    /**
     * Reply inside an open conversation.
     */
    public function reply(Request $request, UserAccount $partner): RedirectResponse
    {
        $request->validate(['message_text' => self::TEXT_RULES], self::TEXT_MESSAGES);

        return $this->deliver($request->user(), $partner, $request->input('message_text'));
    }

    private function deliver(UserAccount $me, UserAccount $recipient, string $text): RedirectResponse
    {
        try {
            $this->messaging->send($me, $recipient, $text);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'Could not send message. Please try again.');
        }

        return redirect()->route('messages.show', $recipient)->with('success', 'Message sent.');
    }

    private function render(UserAccount $me, ?UserAccount $partner = null, bool $compose = false, string $search = ''): View
    {
        $conversations = [];
        $thread = collect();
        $messageable = collect();
        $unreadTotal = 0;
        $loadError = null;

        try {
            if ($partner) {
                $this->messaging->markRead($me, $partner);
                $thread = $this->messaging->thread($me, $partner);
            }
            $conversations = $this->messaging->conversations($me);
            $unreadTotal = $this->messaging->unreadCount($me);
            if ($compose && ! $partner) {
                $messageable = $this->messaging->messageableUsers($me, $search);
            }
        } catch (Throwable $e) {
            report($e);
            $loadError = 'Unable to load messages.';
        }

        return view('messages.index', [
            'me' => $me,
            'partner' => $partner,
            'partnerId' => (int) $partner?->User_ID,
            'thread' => $thread,
            'conversations' => $conversations,
            'unreadTotal' => $unreadTotal,
            'composeMode' => $compose,
            'messageable' => $messageable,
            'search' => $search,
            'loadError' => $loadError,
        ]);
    }
}
