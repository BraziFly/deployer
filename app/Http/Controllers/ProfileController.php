<?php

namespace REBELinBLUE\Deployer\Http\Controllers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\Facades\Image;
use MicheleAngioni\MultiLanguage\LanguageManager;
use PragmaRX\Google2FA\Contracts\Google2FA as Google2FA;
use REBELinBLUE\Deployer\Events\EmailChangeRequested;
use REBELinBLUE\Deployer\Http\Requests\StoreProfileRequest;
use REBELinBLUE\Deployer\Http\Requests\StoreSettingsRequest;
use REBELinBLUE\Deployer\Repositories\Contracts\UserRepositoryInterface;
use REBELinBLUE\Deployer\Settings;

/**
 * The use profile controller.
 */
class ProfileController extends Controller
{
    /**
     * @var UserRepositoryInterface
     */
    private $repository;

    /**
     * @var Google2FA
     */
    private $google2fa;

    /**
     * @var LanguageManager
     */
    private $languageManager;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var ViewFactory
     */
    private $view;

    /**
     * @var Translator
     */
    private $translator;

    /**
     * @var Redirector
     */
    private $redirect;

    /**
     * ProfileController constructor.
     *
     * @param UserRepositoryInterface $repository
     * @param Google2FA               $google2fa
     * @param LanguageManager         $languageManager
     * @param Settings                $settings
     * @param ViewFactory             $view
     * @param Translator              $translator
     * @param Redirector              $redirector
     */
    public function __construct(
        UserRepositoryInterface $repository,
        Google2FA $google2fa,
        LanguageManager $languageManager,
        Settings $settings,
        ViewFactory $view,
        Translator $translator,
        Redirector $redirector
    ) {
        $this->repository      = $repository;
        $this->google2fa       = $google2fa;
        $this->languageManager = $languageManager;
        $this->settings        = $settings;
        $this->view            = $view;
        $this->translator      = $translator;
        $this->redirect        = $redirector;
    }

    /**
     * View user profile.
     *
     * @param Request $request
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $code = $this->google2fa->generateSecretKey();
        if ($user->has_two_factor_authentication || $request->old('google_code')) {
            $code = $request->old('google_code', $user->google2fa_secret);
        }

        $img = $this->google2fa->getQRCodeGoogleUrl('Deployer', $user->email, $code);

        return $this->view->make('user.profile', [
            'google_2fa_url'  => $img,
            'google_2fa_code' => $code,
            'title'           => $this->translator->trans('users.update_profile'),
            'locales'         => $this->languageManager->getAvailableLanguages(),
            'settings'        => $this->settings,
        ]);
    }

    /**
     * Update user's basic profile.
     *
     * @param StoreProfileRequest $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(StoreProfileRequest $request)
    {
        $this->repository->updateById($request->only(
            'name',
            'password'
        ), Auth::user()->id);

        return $this->redirect->to('/');
    }

    /**
     * Update user's settings.
     *
     * @param StoreSettingsRequest $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function settings(StoreSettingsRequest $request)
    {
        $this->repository->updateById($request->only(
            'skin',
            'scheme',
            'language'
        ), Auth::user()->id);

        return $this->redirect->to('/');
    }

    /**
     * Send email to change a new email.
     *
     * @param  Dispatcher $dispatcher
     * @return string
     */
    public function requestEmail(Dispatcher $dispatcher)
    {
        $dispatcher->dispatch(new EmailChangeRequested(Auth::user()));

        return 'success';
    }

    /**
     * Show the page to input the new email.
     *
     * @param string $token
     *
     * @return \Illuminate\View\View
     */
    public function email($token)
    {
        return $this->view->make('user.change-email', [
            'token' => $token,
        ]);
    }

    /**
     * Change the user's email.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function changeEmail(Request $request)
    {
        $user = $this->repository->findByEmailToken($request->get('token'));

        if ($request->get('email')) {
            $user->email       = $request->get('email');
            $user->email_token = '';

            $user->save();
        }

        return $this->redirect->to('/');
    }

    /**
     * Upload file.
     *
     * @param Request $request
     *
     * @param  UrlGenerator $url
     * @return array|string
     */
    public function upload(Request $request, UrlGenerator $url)
    {
        $this->validate($request, [
            'file' => 'required|image',
        ]);

        if ($request->hasFile('file') && $request->file('file')->isValid()) {
            $file            = $request->file('file');
            $path            = '/storage/' . date('Y-m-d');
            $destinationPath = public_path() . $path;
            $filename        = uniqid() . '.' . $file->getClientOriginalExtension();

            $file->move($destinationPath, $filename);

            return [
                'image'   => $url->to($path . '/' . $filename),
                'path'    => $path . '/' . $filename,
                'message' => 'success',
            ];
        }

        return 'failed';
    }

    /**
     * Reset the user's avatar to gravatar.
     *
     * @return array
     */
    public function gravatar()
    {
        $user         = Auth::user();
        $user->avatar = null;
        $user->save();

        return [
            'image'   => $user->avatar_url,
            'success' => true,
        ];
    }

    /**
     * Set and crop the avatar.
     *
     * @param Request $request
     *
     * @param  UrlGenerator $url
     * @return array
     */
    public function avatar(Request $request, UrlGenerator $url)
    {
        $path   = $request->get('path', '/placeholder.jpg');
        $image  = Image::make(public_path() . $path);
        $rotate = $request->get('dataRotate');

        if ($rotate) {
            $image->rotate($rotate);
        }

        $width  = $request->get('dataWidth');
        $height = $request->get('dataHeight');
        $left   = $request->get('dataX');
        $top    = $request->get('dataY');

        $image->crop($width, $height, $left, $top);
        $path = '/storage/' . date('Y-m-d') . '/avatar' . uniqid() . '.jpg';

        $image->save(public_path() . $path);

        $user         = Auth::user();
        $user->avatar = $path;
        $user->save();

        return [
            'image'   => $url->to($path),
            'success' => true,
        ];
    }

    /**
     * Activates two factor authentication.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function twoFactor(Request $request)
    {
        $secret = null;
        if ($request->has('two_factor')) {
            $secret = $request->get('google_code');

            if (!$this->google2fa->verifyKey($secret, $request->get('2fa_code'))) {
                $secret = null;

                return $this->redirect->back()
                                      ->withInput($request->only('google_code', 'two_factor'))
                                      ->withError($this->translator->trans('auth.invalid_code'));
            }
        }

        $user                   = Auth::user();
        $user->google2fa_secret = $secret;
        $user->save();

        return $this->redirect->to('/');
    }
}
