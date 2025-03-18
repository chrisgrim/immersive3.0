@extends('layouts.master-container')

@section('meta')
    <title>Terms of Service - {{config('app.name')}}</title>
    <meta name="description" content="Terms and conditions for using the Everything Immersive platform.">
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
    <h1 class="text-4xl font-bold mb-8">Terms of Service</h1>
    
    <p class="text-lg text-neutral-600 mb-12">
        Last Updated: {{now()->format('F d, Y')}}
    </p>
    
    <div class="prose prose-lg max-w-none">
        <section class="mb-10">
            <h2 class="text-2xl font-semibold mb-4">1. Acceptance of Terms</h2>
            <p>
                Welcome to Everything Immersive. By accessing or using our website, mobile applications, or any of our services, you agree to be bound by these Terms of Service. If you do not agree to these terms, please do not use our services.
            </p>
        </section>
        
        <section class="mb-10">
            <h2 class="text-2xl font-semibold mb-4">2. Description of Service</h2>
            <p>
                Everything Immersive is a platform that connects users with immersive experiences and events. We provide a marketplace for discovering, promoting, and purchasing tickets to these experiences.
            </p>
            <p class="mt-4">
                We do not guarantee the accuracy, completeness, or quality of any events listed on our platform. The content and information about events are provided by event organizers, and we are not responsible for any inaccuracies.
            </p>
        </section>
        
        <section class="mb-10">
            <h2 class="text-2xl font-semibold mb-4">3. User Accounts</h2>
            <p>
                To use certain features of our service, you may need to create an account. You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account.
            </p>
            <p class="mt-4">
                You agree to notify us immediately of any unauthorized use of your account or any other breach of security. We cannot and will not be liable for any loss or damage arising from your failure to comply with this section.
            </p>
        </section>
        
        <section class="mb-10">
            <h2 class="text-2xl font-semibold mb-4">4. User Content</h2>
            <p>
                Our service may allow you to post, link, share, or otherwise make available content, including but not limited to text, images, and videos ("User Content").
            </p>
            <p class="mt-4">
                By making any User Content available through the Service, you grant us a non-exclusive, transferable, sublicensable, worldwide, royalty-free license to use, copy, modify, create derivative works based upon, distribute, publicly display, and publicly perform your User Content.
            </p>
            <p class="mt-4">
                You are solely responsible for your User Content and the consequences of posting or publishing it. You represent and warrant that you own or have the necessary rights to post your User Content and that it does not violate the rights of any third party.
            </p>
        </section>
        
        <section class="mb-10">
            <h2 class="text-2xl font-semibold mb-4">5. Prohibited Activities</h2>
            <p>
                You agree not to engage in any of the following activities:
            </p>
            <ul class="list-disc pl-8 mt-4">
                <li>Violating any applicable laws or regulations</li>
                <li>Impersonating any person or entity or falsely stating your affiliation with a person or entity</li>
                <li>Interfering with or disrupting the services or servers or networks connected to the services</li>
                <li>Posting or transmitting any content that is unlawful, harmful, threatening, abusive, harassing, defamatory, vulgar, obscene, or invasive of another's privacy</li>
                <li>Attempting to gain unauthorized access to other user accounts or any part of the services</li>
                <li>Using automated means to access or collect data from the services without our prior written consent</li>
                <li>Reselling or attempting to resell tickets at prices higher than their face value, unless explicitly permitted by applicable law</li>
            </ul>
        </section>
        
        <section class="mb-10">
            <h2 class="text-2xl font-semibold mb-4">6. Payments and Refunds</h2>
            <p>
                When you purchase tickets through our platform, you agree to pay all fees and applicable taxes. All payments are processed through our secure payment system.
            </p>
            <p class="mt-4">
                Refund policies are determined by event organizers and will be indicated at the time of purchase. Everything Immersive is not responsible for providing refunds unless required by law.
            </p>
        </section>
        
        <section class="mb-10">
            <h2 class="text-2xl font-semibold mb-4">7. Intellectual Property</h2>
            <p>
                The content on our platform, including text, graphics, logos, and images, is owned by Everything Immersive or our licensors and is protected by copyright, trademark, and other intellectual property laws.
            </p>
            <p class="mt-4">
                You may not reproduce, distribute, modify, create derivative works of, publicly display, or in any way exploit any of the content in whole or in part without our prior written consent.
            </p>
        </section>
        
        <section class="mb-10">
            <h2 class="text-2xl font-semibold mb-4">8. Limitation of Liability</h2>
            <p>
                In no event shall Everything Immersive, its officers, directors, employees, or agents, be liable to you for any direct, indirect, incidental, special, punitive, or consequential damages whatsoever resulting from:
            </p>
            <ul class="list-disc pl-8 mt-4">
                <li>Errors, mistakes, or inaccuracies of content</li>
                <li>Personal injury or property damage related to your use of the service</li>
                <li>Any unauthorized access to or use of our servers and/or any personal information stored therein</li>
                <li>Any interruption or cessation of transmission to or from our service</li>
                <li>Any bugs, viruses, or the like that may be transmitted to or through our service</li>
                <li>Any content or conduct of any third party on the service</li>
            </ul>
            <p class="mt-4">
                This limitation of liability applies to the fullest extent permitted by law in the applicable jurisdiction.
            </p>
        </section>
        
        <section class="mb-10">
            <h2 class="text-2xl font-semibold mb-4">9. Indemnification</h2>
            <p>
                You agree to defend, indemnify, and hold harmless Everything Immersive and its subsidiaries, agents, licensors, managers, and other affiliated companies, and their employees, contractors, agents, officers, and directors, from and against any and all claims, damages, obligations, losses, liabilities, costs or debt, and expenses arising from your use of the service.
            </p>
        </section>
        
        <section class="mb-10">
            <h2 class="text-2xl font-semibold mb-4">10. Termination</h2>
            <p>
                We may terminate or suspend your account and bar access to the service immediately, without prior notice or liability, for any reason whatsoever, including without limitation if you breach the Terms.
            </p>
            <p class="mt-4">
                All provisions of the Terms which by their nature should survive termination shall survive termination, including, without limitation, ownership provisions, warranty disclaimers, indemnity, and limitations of liability.
            </p>
        </section>
        
        <section class="mb-10">
            <h2 class="text-2xl font-semibold mb-4">11. Governing Law</h2>
            <p>
                These Terms shall be governed by the laws of the State of California, without respect to its conflict of laws principles. You agree to submit to the personal jurisdiction of the courts located in San Francisco County, California.
            </p>
        </section>
        
        <section class="mb-10">
            <h2 class="text-2xl font-semibold mb-4">12. Changes to Terms</h2>
            <p>
                We reserve the right, at our sole discretion, to modify or replace these Terms at any time. We will provide notice of changes by posting the updated terms on this page. Your continued use of the service after any such changes constitutes your acceptance of the new Terms.
            </p>
        </section>
        
        <section class="mb-10">
            <h2 class="text-2xl font-semibold mb-4">13. Contact Information</h2>
            <p>
                If you have any questions about these Terms, please contact us at:
            </p>
            <p class="mt-4">
                <strong>Email:</strong> legal@everythingimmersive.com<br>
                <strong>Address:</strong> 123 Experience Way, San Francisco, CA 94103
            </p>
        </section>
    </div>
</div>
@endsection

@section('footer')
    @include('footer.footer-padded')
@endsection 