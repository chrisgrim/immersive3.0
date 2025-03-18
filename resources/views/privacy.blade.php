@extends('layouts.master-container')

@section('meta')
    <title>Privacy Policy - {{config('app.name')}}</title>
    <meta name="description" content="Privacy Policy for Everything Immersive - Learn how we collect, use, and protect your personal information.">
@endsection

@section('nav')
@if (Browser::isMobile())
    @include('nav.index-mobile')
@else
    @include('nav.index-desktop')
@endif
@endsection

@section('content')
<div class="mx-auto pt-16 relative h-full max-w-screen-5xl px-8 lg-air:px-16 2xl-air:px-32">
    <h1 class="text-4xl font-bold mb-6">Privacy Policy</h1>
    
    <div class="text-sm text-neutral-500 mb-10">
        Last Updated: {{ date('F d, Y') }}
    </div>
    
    <div class="prose prose-lg max-w-none">
        <p class="lead text-xl text-neutral-600 mb-10">
            At Everything Immersive, we respect your privacy and are committed to protecting your personal data. 
            This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you visit our website 
            or use our services.
        </p>
        
        <div class="space-y-10">
            <!-- Information We Collect -->
            <section>
                <h2 class="text-2xl font-semibold mb-4">1. Information We Collect</h2>
                <div class="pl-4 space-y-4">
                    <h3 class="text-xl font-medium">Personal Information</h3>
                    <p>
                        We may collect personally identifiable information, such as your:
                    </p>
                    <ul class="list-disc pl-5 space-y-2">
                        <li>Name</li>
                        <li>Email address</li>
                        <li>Phone number</li>
                        <li>Billing address</li>
                        <li>Payment information</li>
                        <li>Account preferences</li>
                        <li>Event attendance history</li>
                    </ul>
                    
                    <h3 class="text-xl font-medium mt-6">Non-Personal Information</h3>
                    <p>
                        We may also collect non-personal information about you when you visit our website, including:
                    </p>
                    <ul class="list-disc pl-5 space-y-2">
                        <li>Browser type</li>
                        <li>Operating system</li>
                        <li>IP address</li>
                        <li>Pages visited</li>
                        <li>Time and date of visits</li>
                        <li>Referring websites</li>
                        <li>Other browsing statistics</li>
                    </ul>
                </div>
            </section>
            
            <!-- How We Use Your Information -->
            <section>
                <h2 class="text-2xl font-semibold mb-4">2. How We Use Your Information</h2>
                <p>
                    We may use the information we collect for various purposes, including to:
                </p>
                <ul class="list-disc pl-5 space-y-2 mt-4">
                    <li>Provide, operate, and maintain our services</li>
                    <li>Process and complete transactions</li>
                    <li>Send transactional messages, including confirmations, technical notices, updates, and support messages</li>
                    <li>Respond to your comments, questions, and requests</li>
                    <li>Personalize your experience and deliver content relevant to your interests</li>
                    <li>Monitor and analyze trends, usage, and activities in connection with our services</li>
                    <li>Detect, investigate, and prevent fraudulent transactions and other illegal activities</li>
                    <li>Improve our website and services</li>
                    <li>Send you marketing communications (with your consent where required by law)</li>
                </ul>
            </section>
            
            <!-- Sharing Your Information -->
            <section>
                <h2 class="text-2xl font-semibold mb-4">3. Sharing Your Information</h2>
                <p>
                    We may share your information in the following situations:
                </p>
                <ul class="list-disc pl-5 space-y-2 mt-4">
                    <li><strong>With Event Organizers:</strong> When you purchase tickets or register for events, we share necessary information with the event organizers.</li>
                    <li><strong>With Service Providers:</strong> We may share your information with third-party vendors, service providers, contractors, or agents who perform services for us.</li>
                    <li><strong>Business Transfers:</strong> If we're involved in a merger, acquisition, or sale of assets, your information may be transferred as part of that transaction.</li>
                    <li><strong>Legal Requirements:</strong> We may disclose your information if required to do so by law or in response to valid requests by public authorities.</li>
                    <li><strong>With Your Consent:</strong> We may share your information with your consent or at your direction.</li>
                </ul>
            </section>
            
            <!-- Your Rights -->
            <section>
                <h2 class="text-2xl font-semibold mb-4">4. Your Rights</h2>
                <p>
                    Depending on your location, you may have certain rights regarding your personal information, including:
                </p>
                <ul class="list-disc pl-5 space-y-2 mt-4">
                    <li>The right to access your personal information</li>
                    <li>The right to rectify inaccurate personal information</li>
                    <li>The right to request the deletion of your personal information</li>
                    <li>The right to restrict the processing of your personal information</li>
                    <li>The right to data portability</li>
                    <li>The right to object to the processing of your personal information</li>
                    <li>The right to withdraw consent</li>
                </ul>
                <p class="mt-4">
                    To exercise these rights, please contact us using the information provided at the end of this policy.
                </p>
            </section>
            
            <!-- Data Security -->
            <section>
                <h2 class="text-2xl font-semibold mb-4">5. Data Security</h2>
                <p>
                    We implement appropriate technical and organizational security measures to protect your personal information from unauthorized access, disclosure, alteration, and destruction. However, no method of transmission over the Internet or electronic storage is 100% secure, and we cannot guarantee absolute security.
                </p>
            </section>
            
            <!-- Cookies and Tracking Technologies -->
            <section>
                <h2 class="text-2xl font-semibold mb-4">6. Cookies and Tracking Technologies</h2>
                <p>
                    We use cookies and similar tracking technologies to track activity on our website and hold certain information. Cookies are files with a small amount of data that may include an anonymous unique identifier. You can instruct your browser to refuse all cookies or to indicate when a cookie is being sent.
                </p>
            </section>
            
            <!-- International Transfers -->
            <section>
                <h2 class="text-2xl font-semibold mb-4">7. International Transfers</h2>
                <p>
                    Your information may be transferred to — and maintained on — computers located outside of your state, province, country, or other governmental jurisdiction where the data protection laws may differ from those of your jurisdiction. If you are located outside the United States and choose to provide information to us, please note that we transfer the data to the United States and process it there.
                </p>
            </section>
            
            <!-- Children's Privacy -->
            <section>
                <h2 class="text-2xl font-semibold mb-4">8. Children's Privacy</h2>
                <p>
                    Our services are not intended for individuals under the age of 18. We do not knowingly collect personal information from children under 18. If we learn we have collected personal information from a child under 18, we will delete that information as quickly as possible.
                </p>
            </section>
            
            <!-- Changes to This Privacy Policy -->
            <section>
                <h2 class="text-2xl font-semibold mb-4">9. Changes to This Privacy Policy</h2>
                <p>
                    We may update our Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last Updated" date at the top of this page. You are advised to review this Privacy Policy periodically for any changes.
                </p>
            </section>
            
            <!-- Contact Us -->
            <section>
                <h2 class="text-2xl font-semibold mb-4">10. Contact Us</h2>
                <p>
                    If you have any questions about this Privacy Policy, please contact us at:
                </p>
                <div class="bg-neutral-50 p-6 rounded-xl mt-4 inline-block">
                    <div class="not-prose">
                        <p class="font-medium">Everything Immersive</p>
                        <p>1234 Experience Street</p>
                        <p>San Francisco, CA 94103</p>
                        <p class="mt-2">Email: <a href="mailto:privacy@everythingimmersive.com" class="text-blue-600 hover:underline">privacy@everythingimmersive.com</a></p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection

@section('footer')
    @include('footer.footer-padded')
@endsection 