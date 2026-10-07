import {
    Blocks, BookOpen, Bot, Briefcase, Calendar, Circle, Database, FileText, Folder, Globe, Home, Image, Inbox,
    LayoutGrid, LayoutTemplate, Library, ListTree, Megaphone, Newspaper, Settings, ShoppingBag, Shield, Star, Tags, Users,
    type LucideIcon,
} from 'lucide-react';

/**
 * Icons available to the sidebar. Navigation items name an icon in
 * kebab-case (collections may set `settings.icon`); unknown names fall back
 * to a neutral dot so the bundle only ships the icons listed here.
 */
const icons: Record<string, LucideIcon> = {
    blocks: Blocks,
    'book-open': BookOpen,
    bot: Bot,
    briefcase: Briefcase,
    calendar: Calendar,
    database: Database,
    'file-text': FileText,
    folder: Folder,
    globe: Globe,
    home: Home,
    image: Image,
    inbox: Inbox,
    'layout-grid': LayoutGrid,
    'layout-template': LayoutTemplate,
    library: Library,
    'list-tree': ListTree,
    megaphone: Megaphone,
    newspaper: Newspaper,
    settings: Settings,
    'shopping-bag': ShoppingBag,
    shield: Shield,
    star: Star,
    tags: Tags,
    users: Users,
};

/** Icon names a collection can pick in its settings. */
export const navIconNames = Object.keys(icons);

export function navIcon(name?: string): LucideIcon {
    return (name && icons[name]) || Circle;
}
