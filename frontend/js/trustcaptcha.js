console.log("This site is protected by TrustCaptcha GmbH.");

const script = document.createElement('script');
script.src = "https://cdn.trustcomponent.com/trustcaptcha/2.1.x/trustcaptcha.umd.min.js";
script.async = true;
document.head.appendChild(script);

document.addEventListener("DOMContentLoaded", () => {
    const trustcaptchaComponent = document.getElementsByTagName('trustcaptcha-component')[0];
    if (!trustcaptchaComponent) return;

    trustcaptchaComponent.addEventListener('captchaSolved', (event) => {
        console.log('Verification token:', event.detail);
    });

    trustcaptchaComponent.addEventListener('captchaFailed', (event) => {
        console.error(event.detail);
    });
});
