
const Index = () => import('./components/l-limitless-bs4/Index');
const CreateFormsMake = () => import('./components/l-limitless-bs4/create-forms/Make');
const Show = () => import('./components/l-limitless-bs4/Show');
const Edit = () => import('./components/l-limitless-bs4/Edit');
const SideBarLeft = () => import('./components/l-limitless-bs4/SideBarLeft');
const SideBarRight = () => import('./components/l-limitless-bs4/SideBarRight');

const routes = [

    {
        path: '/receipts',
        components: {
            default: Index,
            //'sidebar-left': ComponentSidebarLeft,
            //'sidebar-right': ComponentSidebarRight
        },
        meta: {
            title: 'Accounting :: Sales :: Receipts',
            metaTags: [
                {
                    name: 'description',
                    content: 'Receipts'
                },
                {
                    property: 'og:description',
                    content: 'Receipts'
                }
            ]
        }
    },
    {
        path: '/receipts/create',
        components: {
            default: CreateFormsMake,
            //'sidebar-left': ComponentSidebarLeft,
            //'sidebar-right': ComponentSidebarRight
        },
        meta: {
            title: 'Accounting :: Sales :: Receipt :: Create',
            metaTags: [
                {
                    name: 'description',
                    content: 'Create Receipt'
                },
                {
                    property: 'og:description',
                    content: 'Create Receipt'
                }
            ]
        }
    },
    {
        path: '/receipts/:id',
        components: {
            default: Show,
            'sidebar-left': SideBarLeft,
            'sidebar-right': SideBarRight
        },
        meta: {
            title: 'Accounting :: Sales :: Receipt',
            metaTags: [
                {
                    name: 'description',
                    content: 'Receipt'
                },
                {
                    property: 'og:description',
                    content: 'Receipt'
                }
            ]
        }
    },
    {
        path: '/receipts/:id/copy',
        components: {
            default: CreateFormsMake,
        },
        meta: {
            title: 'Accounting :: Sales :: Receipt :: Copy',
            metaTags: [
                {
                    name: 'description',
                    content: 'Copy Receipt'
                },
                {
                    property: 'og:description',
                    content: 'Copy Receipt'
                }
            ]
        }
    },
    {
        path: '/receipts/:id/edit',
        components: {
            default: Edit,
        },
        meta: {
            title: 'Receipt :: Edit',
            metaTags: [
                {
                    name: 'description',
                    content: 'Edit Receipt'
                },
                {
                    property: 'og:description',
                    content: 'Edit Receipt'
                }
            ]
        }
    }

]

export default routes
