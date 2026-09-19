# 10 — File Map

Every Vue file in the product, and where it is documented. Use this to confirm
nothing was missed, and to point developers at the right source file.

## Layouts

| File | Used by | Documented in |
|---|---|---|
| `Layouts/AppLayout.vue` | All 7 in-workspace pages | `03-SCREENS/00-APP-SHELL.md` |
| `Layouts/AuthLayout.vue` | All 5 auth pages | `03-SCREENS/01-AUTH.md` |
| `Layouts/GuestLayout.vue` | Workspace Create only | `03-SCREENS/02-WORKSPACE.md` |

## Pages (14)

| File | Screen | Documented in |
|---|---|---|
| `Pages/Auth/Login.vue` | Login | `03-SCREENS/01-AUTH.md` → 01a |
| `Pages/Auth/Register.vue` | Register | `03-SCREENS/01-AUTH.md` → 01b |
| `Pages/Auth/ForgotPassword.vue` | Forgot Password | `03-SCREENS/01-AUTH.md` → 01c |
| `Pages/Auth/ResetPassword.vue` | Reset Password | `03-SCREENS/01-AUTH.md` → 01d |
| `Pages/Auth/VerifyEmail.vue` | Verify Email | `03-SCREENS/01-AUTH.md` → 01e |
| `Pages/Workspace/Index.vue` | Workspace picker | `03-SCREENS/02-WORKSPACE.md` → 02a |
| `Pages/Workspace/Create.vue` | Create wizard | `03-SCREENS/02-WORKSPACE.md` → 02b |
| `Pages/Workspace/Settings.vue` | Workspace settings | `03-SCREENS/02-WORKSPACE.md` → 02c |
| `Pages/Dashboard.vue` | Dashboard | `03-SCREENS/03-DASHBOARD.md` |
| `Pages/Channel/Show.vue` | Channel chat | `03-SCREENS/04-CHANNEL-CHAT.md` |
| `Pages/DirectMessage/Show.vue` | Direct message | `03-SCREENS/05-DIRECT-MESSAGES.md` |
| `Pages/Members/Index.vue` | Members directory | `03-SCREENS/06-MEMBERS.md` |
| `Pages/Notifications/Index.vue` | Notifications page | `03-SCREENS/07-NOTIFICATIONS.md` |
| `Pages/Profile/Show.vue` | Account settings | `03-SCREENS/08-PROFILE.md` |

## Components (29)

| File | Documented in |
|---|---|
| `Chat/ChatArea.vue` | `03-SCREENS/04-CHANNEL-CHAT.md` |
| `Chat/ChatHeader.vue` | `04-CHANNEL-CHAT.md` §1 + `05-DIRECT-MESSAGES.md` |
| `Chat/MessageList.vue` | `04-CHANNEL-CHAT.md` §2 |
| `Chat/MessageItem.vue` | `04-CHANNEL-CHAT.md` § Message anatomy |
| `Chat/MessageReactions.vue` | `04-CHANNEL-CHAT.md` § Reactions |
| `Chat/MessageInput.vue` | `04-CHANNEL-CHAT.md` §4 Composer |
| `Chat/ThreadPanel.vue` | `04-CHANNEL-CHAT.md` §5 |
| `Chat/TypingIndicator.vue` | `04-CHANNEL-CHAT.md` §3 |
| `Chat/ChannelMembersModal.vue` | `04-MODALS-AND-OVERLAYS.md` §2 |
| `Sidebar/AppSidebar.vue` | `03-SCREENS/00-APP-SHELL.md` |
| `Sidebar/WorkspaceSwitcher.vue` | `00-APP-SHELL.md` § Sidebar 1 |
| `Sidebar/ChannelList.vue` | `00-APP-SHELL.md` § Sidebar 3 |
| `Sidebar/DirectMessageList.vue` | `00-APP-SHELL.md` § Sidebar 4 |
| `Sidebar/MemberRow.vue` | `00-APP-SHELL.md` § Sidebar 4 |
| `RightPanel.vue` | `04-MODALS-AND-OVERLAYS.md` §8 + `08-KNOWN-GAPS.md` |
| `Common/Modal.vue` | `04-MODALS-AND-OVERLAYS.md` § Base modal |
| `Common/Avatar.vue` | `05-COMPONENT-LIBRARY.md` |
| `Common/Button.vue` | `05-COMPONENT-LIBRARY.md` |
| `Common/Toast.vue` | `04-MODALS-AND-OVERLAYS.md` §11 |
| `Common/ToastContainer.vue` | `04-MODALS-AND-OVERLAYS.md` §11 |
| `Common/SkeletonLoader.vue` | `06-STATES-AND-EDGE-CASES.md` § Loading |
| `Common/SearchBar.vue` | `00-APP-SHELL.md` § Sidebar 2 |
| `Common/ImageLightbox.vue` | `04-MODALS-AND-OVERLAYS.md` §6 |
| `Common/AvatarCropModal.vue` | `04-MODALS-AND-OVERLAYS.md` §5 |
| `Dashboard/StatsCard.vue` | `03-SCREENS/03-DASHBOARD.md` |
| `Dashboard/MessageChart.vue` | `03-SCREENS/03-DASHBOARD.md` |
| `Notifications/NotificationBell.vue` | `03-SCREENS/07-NOTIFICATIONS.md` → A |
| `Call/CallScreen.vue` | `04-MODALS-AND-OVERLAYS.md` §13 |
| `Call/IncomingCallToast.vue` | `04-MODALS-AND-OVERLAYS.md` §12 |

**Total: 46 Vue files — all covered.**
