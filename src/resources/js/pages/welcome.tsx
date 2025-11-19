import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Podcast Feed - Create Custom RSS Feeds">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
            </Head>
            
            <div className="min-h-screen flex flex-col" style={{ background: 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)' }}>
                {/* Header */}
                <header className="border-b border-white/10 p-5">
                    <div className="max-w-7xl mx-auto flex justify-between items-center">
                        <div className="text-2xl font-semibold text-blue-400">🎙️ Podcast Feed</div>
                        <nav className="flex gap-5">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="bg-blue-500 px-4 py-2 rounded-lg text-white font-medium hover:bg-blue-600 transition-colors"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="text-gray-300 px-4 py-2 rounded-lg hover:bg-white/10 transition-colors"
                                    >
                                        Log in
                                    </Link>
                                    <Link
                                        href={route('register')}
                                        className="bg-blue-500 px-4 py-2 rounded-lg text-white font-medium hover:bg-blue-600 transition-colors"
                                    >
                                        Get Started
                                    </Link>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Main Content */}
                <main className="flex-1 py-20 px-5">
                    <div className="max-w-7xl mx-auto">
                        {/* Hero Section */}
                        <section className="text-center mb-20">
                            <h1 className="text-5xl md:text-7xl font-bold mb-6 bg-gradient-to-r from-blue-400 to-purple-400 bg-clip-text text-transparent">
                                Create Custom Podcast Feeds
                            </h1>
                            <p className="text-xl md:text-2xl text-gray-400 max-w-3xl mx-auto mb-12">
                                Upload audio files or add YouTube links to build personalized RSS feeds for your audience. Simple, fast, and reliable.
                            </p>
                        </section>

                        {/* Features Grid */}
                        <section className="grid md:grid-cols-2 lg:grid-cols-3 gap-8 mb-20">
                            <div className="bg-slate-800/50 border border-white/10 rounded-xl p-8 text-center backdrop-blur-sm">
                                <div className="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-500 rounded-xl flex items-center justify-center mx-auto mb-6 text-2xl">
                                    📁
                                </div>
                                <h3 className="text-xl font-semibold mb-4 text-gray-100">Upload Audio Files</h3>
                                <p className="text-gray-400 leading-relaxed">
                                    Upload MP3, WAV, and other audio formats directly to your library. We handle processing and optimization.
                                </p>
                            </div>

                            <div className="bg-slate-800/50 border border-white/10 rounded-xl p-8 text-center backdrop-blur-sm">
                                <div className="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-500 rounded-xl flex items-center justify-center mx-auto mb-6 text-2xl">
                                    🎥
                                </div>
                                <h3 className="text-xl font-semibold mb-4 text-gray-100">YouTube Integration</h3>
                                <p className="text-gray-400 leading-relaxed">
                                    Simply paste a YouTube link and we'll extract audio automatically. Perfect for repurposing video content.
                                </p>
                            </div>

                            <div className="bg-slate-800/50 border border-white/10 rounded-xl p-8 text-center backdrop-blur-sm">
                                <div className="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-500 rounded-xl flex items-center justify-center mx-auto mb-6 text-2xl">
                                    📡
                                </div>
                                <h3 className="text-xl font-semibold mb-4 text-gray-100">Custom RSS Feeds</h3>
                                <p className="text-gray-400 leading-relaxed">
                                    Create multiple feeds with different content. Each feed gets its own RSS URL for podcast platforms.
                                </p>
                            </div>

                            <div className="bg-slate-800/50 border border-white/10 rounded-xl p-8 text-center backdrop-blur-sm">
                                <div className="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-500 rounded-xl flex items-center justify-center mx-auto mb-6 text-2xl">
                                    🔄
                                </div>
                                <h3 className="text-xl font-semibold mb-4 text-gray-100">Auto-Duplication Detection</h3>
                                <p className="text-gray-400 leading-relaxed">
                                    Smart detection prevents duplicate files, saving you storage space and keeping your feeds clean.
                                </p>
                            </div>

                            <div className="bg-slate-800/50 border border-white/10 rounded-xl p-8 text-center backdrop-blur-sm">
                                <div className="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-500 rounded-xl flex items-center justify-center mx-auto mb-6 text-2xl">
                                    ⚡
                                </div>
                                <h3 className="text-xl font-semibold mb-4 text-gray-100">Fast Processing</h3>
                                <p className="text-gray-400 leading-relaxed">
                                    Background processing ensures your files are ready quickly without slowing down your workflow.
                                </p>
                            </div>

                            <div className="bg-slate-800/50 border border-white/10 rounded-xl p-8 text-center backdrop-blur-sm">
                                <div className="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-500 rounded-xl flex items-center justify-center mx-auto mb-6 text-2xl">
                                    👥
                                </div>
                                <h3 className="text-xl font-semibold mb-4 text-gray-100">Multi-User Support</h3>
                                <p className="text-gray-400 leading-relaxed">
                                    Each user has their own private library and feeds. Your content remains secure and separate.
                                </p>
                            </div>
                        </section>

                        {/* Call to Action */}
                        <section className="text-center bg-slate-800/30 border border-white/10 rounded-2xl p-16 backdrop-blur-sm">
                            <h2 className="text-4xl font-bold mb-6 text-gray-100">Ready to Start Your Podcast?</h2>
                            <p className="text-xl text-gray-400 mb-10 max-w-2xl mx-auto">
                                Join creators who are already building their audio presence with our simple platform.
                            </p>
                            <div className="flex flex-col sm:flex-row gap-6 justify-center items-center">
                                <Link
                                    href={route('register')}
                                    className="bg-gradient-to-r from-blue-500 to-purple-500 px-8 py-4 rounded-xl text-white font-semibold text-lg hover:from-blue-600 hover:to-purple-600 transition-all transform hover:-translate-y-1 hover:shadow-lg hover:shadow-blue-500/25"
                                >
                                    Create Free Account
                                </Link>
                                <Link
                                    href={route('login')}
                                    className="border border-blue-400 text-blue-400 px-8 py-4 rounded-xl font-semibold text-lg hover:bg-blue-400/10 transition-colors"
                                >
                                    Sign In
                                </Link>
                            </div>
                        </section>
                    </div>
                </main>
            </div>
        </>
    );
}