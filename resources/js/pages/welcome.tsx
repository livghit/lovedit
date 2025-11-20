import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import {

} from '@/components/ui/card';
import { dashboard, login, register } from '@/routes';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BookOpenCheck,
    CheckCircle2,
    Github,
    Globe2,
    Lock,
    Search,
    Sparkles,
    Twitter,
    Zap,
} from 'lucide-react';

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth, name, quote } = usePage<SharedData>().props;

    return (
        <>
            <Head title={`${name} – Enterprise Grade Reading Companion`} />

            <div className="min-h-screen bg-background text-foreground selection:bg-primary selection:text-primary-foreground font-sans antialiased">
                {/* Navbar */}
                <header className="fixed top-0 left-0 right-0 z-50 border-b border-white/10 bg-background/80 backdrop-blur-md">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-6 lg:px-8">
                        <Link href={dashboard()} className="flex items-center gap-2">
                            <div className="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                <AppLogoIcon className="size-5 fill-current" />
                            </div>
                            <span className="text-lg font-bold tracking-tight">{name}</span>
                        </Link>

                        <nav className="hidden md:flex items-center gap-8">
                            {/* Placeholder for future sections */}
                        </nav>

                        <div className="flex items-center gap-4">
                            {auth.user ? (
                                <Link href={dashboard()}>
                                    <Button variant="default" size="sm">Dashboard</Button>
                                </Link>
                            ) : (
                                <>
                                    <Link href={login()} className="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">
                                        Log in
                                    </Link>
                                    {canRegister && (
                                        <Link href={register()}>
                                            <Button size="sm">Get Started</Button>
                                        </Link>
                                    )}
                                </>
                            )}
                        </div>
                    </div>
                </header>

                <main>
                    {/* Hero Section */}
                    <section className="relative isolate pt-32 pb-20 lg:pt-40 lg:pb-32 overflow-hidden">
                        {/* Background Effects */}
                        <div className="absolute inset-0 -z-10 h-full w-full bg-[linear-gradient(to_right,#80808012_1px,transparent_1px),linear-gradient(to_bottom,#80808012_1px,transparent_1px)] bg-[size:24px_24px] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)]"></div>
                        <div className="absolute top-0 left-1/2 -z-10 h-[500px] w-[500px] -translate-x-1/2 rounded-full bg-primary/20 blur-[100px] opacity-50"></div>

                        <div className="mx-auto max-w-7xl px-6 text-center lg:px-8">
                            <div className="mx-auto mb-8 flex max-w-fit items-center justify-center space-x-2 overflow-hidden rounded-full border border-primary/20 bg-primary/10 px-4 py-1.5 backdrop-blur transition-all hover:border-primary/40 hover:bg-primary/20">
                                <Sparkles className="size-4 text-primary" />
                                <p className="text-sm font-semibold text-primary">
                                    Introducing Hybrid Search v2.0
                                </p>
                            </div>

                            <h1 className="mx-auto max-w-4xl text-5xl font-bold tracking-tight text-foreground sm:text-7xl">
                                Master your reading list with <span className="text-primary">precision</span>.
                            </h1>

                            <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-muted-foreground">
                                The enterprise-grade platform for serious readers. Track, review, and remember every book with our advanced recall engine and seamless library synchronization.
                            </p>

                            <div className="mt-10 flex items-center justify-center gap-x-6">
                                <Link href={auth.user ? dashboard() : register()}>
                                    <Button size="lg" className="h-12 px-8 text-base shadow-lg shadow-primary/25">
                                        {auth.user ? 'Go to Dashboard' : 'Start Free Trial'}
                                        <ArrowRight className="ml-2 size-4" />
                                    </Button>
                                </Link>
                                {!auth.user && (
                                    <Link href={login()} className="text-sm font-semibold leading-6 text-foreground hover:text-primary transition-colors">
                                        Existing customer? <span aria-hidden="true">→</span>
                                    </Link>
                                )}
                            </div>

                            {/* Hero Image / Dashboard Preview */}
                            <div className="mt-16 flow-root sm:mt-24">
                                <div className="-m-2 rounded-xl bg-gray-900/5 p-2 ring-1 ring-inset ring-gray-900/10 dark:bg-white/5 dark:ring-white/10 lg:-m-4 lg:rounded-2xl lg:p-4">
                                    <div className="relative rounded-lg bg-card shadow-2xl overflow-hidden border border-border/50 aspect-[16/9]">
                                        <div className="absolute inset-0 bg-gradient-to-tr from-primary/5 via-background to-background z-0"></div>
                                        <div className="relative z-10 flex h-full items-center justify-center text-muted-foreground">
                                            <div className="text-center">
                                                <AppLogoIcon className="mx-auto size-20 opacity-20" />
                                                <p className="mt-4 font-medium">Dashboard Preview</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* Stats Section */}
                    <section className="border-y border-white/5 bg-white/5 py-12">
                        <div className="mx-auto max-w-7xl px-6 lg:px-8">
                            <dl className="grid grid-cols-1 gap-x-8 gap-y-16 text-center lg:grid-cols-3">
                                <div className="mx-auto flex max-w-xs flex-col gap-y-4">
                                    <dt className="text-base leading-7 text-muted-foreground">Books Tracked</dt>
                                    <dd className="order-first text-3xl font-semibold tracking-tight text-foreground sm:text-5xl">10k+</dd>
                                </div>
                                <div className="mx-auto flex max-w-xs flex-col gap-y-4">
                                    <dt className="text-base leading-7 text-muted-foreground">Active Readers</dt>
                                    <dd className="order-first text-3xl font-semibold tracking-tight text-foreground sm:text-5xl">50k+</dd>
                                </div>
                                <div className="mx-auto flex max-w-xs flex-col gap-y-4">
                                    <dt className="text-base leading-7 text-muted-foreground">Reviews Written</dt>
                                    <dd className="order-first text-3xl font-semibold tracking-tight text-foreground sm:text-5xl">120k+</dd>
                                </div>
                            </dl>
                        </div>
                    </section>

                    {/* Features Grid */}
                    <section id="features" className="py-24 sm:py-32">
                        <div className="mx-auto max-w-7xl px-6 lg:px-8">
                            <div className="mx-auto max-w-2xl text-center">
                                <h2 className="text-base font-semibold leading-7 text-primary">Everything you need</h2>
                                <p className="mt-2 text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
                                    Built for the modern intellectual
                                </p>
                                <p className="mt-6 text-lg leading-8 text-muted-foreground">
                                    Our platform provides the tools you need to build a digital library that lasts a lifetime.
                                </p>
                            </div>

                            <div className="mx-auto mt-16 max-w-2xl sm:mt-20 lg:mt-24 lg:max-w-none">
                                <div className="grid max-w-xl grid-cols-1 gap-x-8 gap-y-16 lg:max-w-none lg:grid-cols-3">
                                    <FeatureCard
                                        icon={BookOpenCheck}
                                        title="Beautiful Reviews"
                                        description="Compose thoughtful reviews with our distraction-free editor. Support for markdown, rich formatting, and embedded media."
                                    />
                                    <FeatureCard
                                        icon={Search}
                                        title="Hybrid Search Engine"
                                        description="Instantly search across your local library and millions of online books simultaneously. Never miss a title."
                                    />
                                    <FeatureCard
                                        icon={Lock}
                                        title="Private & Secure"
                                        description="Your data is yours. We use enterprise-grade encryption and offer full data export capabilities at any time."
                                    />
                                    <FeatureCard
                                        icon={Zap}
                                        title="Instant Sync"
                                        description="Changes reflect instantly across all your devices. Start reading on your phone, finish reviewing on your desktop."
                                    />
                                    <FeatureCard
                                        icon={Globe2}
                                        title="Global Database"
                                        description="Access metadata for over 30 million books. Automatic cover art, author details, and publication info."
                                    />
                                    <FeatureCard
                                        icon={CheckCircle2}
                                        title="Reading Goals"
                                        description="Set annual reading challenges and track your progress with beautiful visualizations and insights."
                                    />
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* Quote Section */}
                    <section className="relative isolate overflow-hidden bg-primary/5 px-6 py-24 sm:py-32 lg:px-8">
                        <div className="absolute inset-0 -z-10 bg-[radial-gradient(45rem_50rem_at_top,theme(colors.primary.DEFAULT),transparent)] opacity-10" />
                        <div className="absolute inset-y-0 right-1/2 -z-10 mr-16 w-[200%] origin-bottom-left skew-x-[-30deg] bg-background shadow-xl shadow-primary/10 ring-1 ring-primary/10 sm:mr-28 lg:mr-0 xl:mr-16 xl:origin-center" />

                        <div className="mx-auto max-w-2xl lg:max-w-4xl">
                            <figure className="mt-10">
                                <blockquote className="text-center text-xl font-semibold leading-8 text-foreground sm:text-2xl sm:leading-9">
                                    <p>“{quote.message}”</p>
                                </blockquote>
                                <figcaption className="mt-10">
                                    <div className="mt-4 flex items-center justify-center space-x-3 text-base">
                                        <div className="font-semibold text-foreground">{quote.author}</div>
                                    </div>
                                </figcaption>
                            </figure>
                        </div>
                    </section>
                </main>

                {/* Footer */}
                <footer className="bg-background border-t border-white/10">
                    <div className="mx-auto max-w-7xl px-6 py-12 md:flex md:items-center md:justify-between lg:px-8">
                        <div className="flex justify-center space-x-6 md:order-2">
                            <a href="#" className="text-muted-foreground hover:text-foreground">
                                <span className="sr-only">Twitter</span>
                                <Twitter className="h-6 w-6" />
                            </a>
                            <a href="#" className="text-muted-foreground hover:text-foreground">
                                <span className="sr-only">GitHub</span>
                                <Github className="h-6 w-6" />
                            </a>
                        </div>
                        <div className="mt-8 md:order-1 md:mt-0">
                            <p className="text-center text-xs leading-5 text-muted-foreground">
                                &copy; {new Date().getFullYear()} {name}. All rights reserved. Built on Laravel & Inertia.
                            </p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}

function FeatureCard({ icon: Icon, title, description }: { icon: any, title: string, description: string }) {
    return (
        <div className="flex flex-col bg-card/50 p-6 rounded-2xl border border-white/5 hover:border-primary/50 transition-colors">
            <dt className="flex items-center gap-x-3 text-base font-semibold leading-7 text-foreground">
                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                    <Icon className="h-6 w-6 text-primary" aria-hidden="true" />
                </div>
                {title}
            </dt>
            <dd className="mt-4 flex flex-auto flex-col text-base leading-7 text-muted-foreground">
                <p className="flex-auto">{description}</p>
            </dd>
        </div>
    );
}
