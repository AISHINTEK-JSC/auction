<?php

namespace App\Filament\Pages;

use App\Notifications\EmailTemplateSample;
use App\Support\AdminLog;
use App\Support\EmailTemplate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Notification as Notifications;
use Throwable;

/**
 * Where an admin shapes the site's emails: the layout they all share, and the
 * subject, body and button of each kind (see App\Support\EmailTemplate).
 */
class EmailTemplatesPage extends Page
{
    protected string $view = 'filament.pages.email-templates';

    protected static ?string $slug = 'email-templates';

    protected static ?string $title = 'Mẫu email';

    protected static ?string $navigationLabel = 'Mẫu email';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static ?int $navigationSort = 11;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $groups = [];

        foreach (EmailTemplate::TYPES as $type => $definition) {
            $content = EmailTemplate::content($type);

            $groups[$definition['group']][$type] = [
                'label' => $definition['label'],
                'subject' => $content['subject'],
                'customized' => $content['customized'],
                'mail_only' => in_array($type, EmailTemplate::MAIL_ONLY, true),
            ];
        }

        return ['groups' => $groups, 'layout' => EmailTemplate::layout()];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editLayout')
                ->label('Sửa giao diện chung')
                ->icon(Heroicon::OutlinedPaintBrush)
                ->modalHeading('Giao diện chung của mọi email')
                ->modalDescription('Dùng được trong lời chào, chữ ký và chân thư: '.$this->placeholders(EmailTemplate::LAYOUT_VARIABLES).'.')
                ->modalSubmitActionLabel('Lưu')
                ->fillForm(fn (): array => [...EmailTemplate::layout(), 'reset' => false])
                ->schema([
                    Toggle::make('show_logo')->label('Hiện logo ở đầu thư')
                        ->helperText('Logo là biểu tượng của site (public/apple-touch-icon.png), đặt cạnh tên website.'),
                    ColorPicker::make('brand_color')->label('Màu chủ đạo')->required()->regex('/^#[0-9a-fA-F]{6}$/')
                        ->helperText('Màu của nút bấm, đường viền trên và liên kết.'),
                    TextInput::make('greeting')->label('Lời chào')->required()->maxLength(120),
                    Textarea::make('signature')->label('Chữ ký cuối thư')->required()->rows(2)->maxLength(300),
                    Textarea::make('footer')->label('Chân thư')->required()->rows(3)->maxLength(600)
                        ->helperText('Dòng chữ nhỏ dưới cùng: bản quyền, địa chỉ, lý do nhận thư.'),
                    Toggle::make('reset')->label('Dùng lại giao diện mặc định')
                        ->helperText('Khi bật, các ô phía trên bị bỏ qua.'),
                ])
                ->action(function (array $data): void {
                    $data['reset'] ? EmailTemplate::resetLayout() : EmailTemplate::saveLayout($data);

                    AdminLog::record('email_template.updated', null, $data['reset']
                        ? 'Đưa giao diện email về mặc định'
                        : 'Sửa giao diện chung của email');

                    Notification::make()->title('Đã lưu giao diện email')->success()->send();
                }),
        ];
    }

    /**
     * Reword one kind of email. Rendered once per row, with the type as argument.
     */
    public function editContentAction(): Action
    {
        return Action::make('editContent')
            ->label('Sửa')
            ->link()
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading(fn (array $arguments): string => 'Sửa mẫu: '.EmailTemplate::TYPES[$arguments['type']]['label'])
            ->modalDescription(fn (array $arguments): string => $this->contentHelp($arguments['type']))
            ->modalSubmitActionLabel('Lưu')
            ->fillForm(fn (array $arguments): array => [
                ...array_diff_key(EmailTemplate::content($arguments['type']), ['customized' => true]),
                'reset' => false,
            ])
            ->schema([
                TextInput::make('subject')->label('Tiêu đề')->required()->maxLength(150),
                Textarea::make('body')->label('Nội dung')->required()->rows(6)->maxLength(2000)
                    ->helperText('Mỗi dòng là một đoạn văn. Chỉ là chữ thường: không dùng HTML hay Markdown.'),
                TextInput::make('button')->label('Nhãn nút bấm')->required()->maxLength(40),
                Toggle::make('reset')->label('Dùng lại nội dung mặc định')
                    ->helperText('Khi bật, các ô phía trên bị bỏ qua.'),
            ])
            ->action(function (array $data, array $arguments): void {
                $type = $arguments['type'];

                $data['reset']
                    ? EmailTemplate::resetContent($type)
                    : EmailTemplate::saveContent($type, $data['subject'], $data['body'], $data['button']);

                AdminLog::record('email_template.updated', null, sprintf(
                    '%s mẫu email "%s"',
                    $data['reset'] ? 'Đưa về mặc định' : 'Sửa',
                    EmailTemplate::TYPES[$type]['label'],
                ));

                Notification::make()->title('Đã lưu mẫu email')->success()->send();
            });
    }

    /**
     * Show the email as it would arrive, filled with sample values.
     */
    public function previewAction(): Action
    {
        return Action::make('preview')
            ->label('Xem trước')
            ->link()
            ->color('gray')
            ->icon(Heroicon::OutlinedEye)
            ->modalHeading(fn (array $arguments): string => 'Xem trước: '.EmailTemplate::TYPES[$arguments['type']]['label'])
            ->modalDescription('Dùng dữ liệu mẫu. Thư thật sẽ có tên người nhận, tên tin và số tiền thật.')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Đóng')
            ->modalContent(function (array $arguments): View {
                $message = EmailTemplate::sample($arguments['type']);

                return view('filament.email-preview', [
                    'subject' => $message->subject,
                    'html' => (string) $message->render(),
                ]);
            });
    }

    /**
     * Send the sample to a real mailbox.
     */
    public function sendTestAction(): Action
    {
        return Action::make('sendTest')
            ->label('Gửi thử')
            ->link()
            ->color('gray')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->modalHeading(fn (array $arguments): string => 'Gửi thử: '.EmailTemplate::TYPES[$arguments['type']]['label'])
            ->modalSubmitActionLabel('Gửi')
            ->fillForm(fn (): array => ['to' => auth()->user()?->email])
            ->schema([TextInput::make('to')->label('Gửi tới')->email()->required()])
            ->action(function (array $data, array $arguments): void {
                try {
                    Notifications::route('mail', $data['to'])
                        ->notifyNow(new EmailTemplateSample($arguments['type'], auth()->user()?->name));
                } catch (Throwable $e) {
                    Notification::make()->title('Không gửi được email')->body($e->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('Đã gửi thư thử tới '.$data['to'])->success()->send();
            });
    }

    /**
     * Where the text is used and which placeholders it understands.
     */
    private function contentHelp(string $type): string
    {
        $where = in_array($type, EmailTemplate::MAIL_ONLY, true)
            ? 'Chỉ gửi qua email.'
            : 'Tiêu đề và nội dung dùng cho cả email lẫn thông báo ở chuông trên site.';

        return $where.' Dùng được: '.$this->placeholders(['site' => 'tên website', ...EmailTemplate::TYPES[$type]['vars']]).'.';
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function placeholders(array $variables): string
    {
        return implode(', ', array_map(
            fn (string $name, string $meaning) => '{'.$name.'} = '.$meaning,
            array_keys($variables),
            $variables,
        ));
    }
}
