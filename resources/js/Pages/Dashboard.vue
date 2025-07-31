<script setup>
/* 
This is the Page Dashboard
Including basic charts for Admin, basic features.
*/
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
import { debounce } from 'lodash';
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    PointElement,
    LineElement,
    Legend,
    BarElement,
    CategoryScale,
    ArcElement,
    LinearScale
} from 'chart.js';
import { Bar, Line, Pie } from 'vue-chartjs';
import { ref, onMounted } from 'vue';
import InputLabel from '@/Components/Default/InputLabel.vue';
import TextInput from '@/Components/Default/TextInput.vue';
import { toast } from 'vue3-toastify';
import('vue3-toastify/dist/index.css');

const api = axios.create({
    baseURL: `http://localhost:8000`,
    withCredentials: true
});

ChartJS.register(CategoryScale, ArcElement, LinearScale, BarElement, PointElement,
    LineElement, Title, Tooltip, Legend);
const getUserMonthlyGrowth = (callback) => {
    api.get(route('dashboard.userMonthlyGrowth')).then((response) => {
        callback(response.data);
    });
}
const dataBar = ref(null);
getUserMonthlyGrowth((data) => {
    /*
     * Callback function for the getUserMonthlyGrowth API call.
     * This function processes the fetched monthly user growth data
     * and updates the 'dataBar' reactive property for chart rendering.
     *
     * @param {Array<Object>} data - An array of objects, where each object
     * represents a month's user growth.
     * Expected structure: [{ month: String, count: Number }, ...]
     */
    dataBar.value = {
        labels: data.map((data) => {
            return data.month;
        }),
        datasets: [{
            data: data.map((data) => {
                return data.count;
            }),
            label: 'User',
            backgroundColor: '#8fce00',
        }],
    };
});
const optionsBar = ref({
    responsive: true
});

/* 
Thread Growing By Category
*/
const pageDataLine = ref(1);
const maxPageDataLine = ref(0);

const getGrowthThreadsData = (callback, page = 1) => {
    /*
     * Fetches monthly growth data for threads by category.
     * This function makes an API call to retrieve paginated thread data
     * and then passes the response data to a provided callback function.
     *
     * @param {function(Object): void} callback - A callback function that will be
     * called with the API response data upon successful retrieval.
     * The expected parameter for the callback is an object containing thread growth data.
     * @param {number} [page=1] - The current page number for pagination. Defaults to 1.
     * @returns {void}
     */
    const url = route('dashboard.getGrowthThreadsData'); // Generates the API endpoint URL for fetching thread growth data.
    
    // Makes an asynchronous GET request to the specified URL, including the current page number.
    api.get(url + '?page=' + page).then((response) => {
        // Upon successful response, invokes the provided callback function,
        // passing the 'data' property from the API response.
        callback(response.data);
    });
};
const dataLine = ref(null);

const setUpDataLine = (page = 1) => {
    /*
     * Prepares and sets the data for a line chart displaying thread growth.
     * This function calls `getGrowthThreadsData` to fetch the necessary data
     * and then formats it appropriately for the `dataLine` reactive property,
     * also updating the maximum page number for pagination.
     *
     * @param {number} [page=1] - The current page number for fetching paginated thread growth data.
     * Defaults to 1.
     * @returns {void}
     */
    // Calls the utility function to fetch paginated thread growth data.
    // The provided callback processes the fetched data to populate the chart.
    getGrowthThreadsData((data) => {
        // Updates the reactive property `maxPageDataLine` with the total number of pages
        // available for the fetched data, typically used for pagination controls.
        maxPageDataLine.value = data.pagination.last_page;

        // Assigns the formatted data to the `dataLine` reactive property,
        // which will be used by a charting library (e.g., Chart.js) to render the line chart.
        dataLine.value = {
            // Sets the labels for the x-axis of the chart (e.g., months).
            labels: data.months,
            // Maps the fetched thread data for each category into an array of datasets,
            // suitable for a multi-line chart.
            datasets:
                data.data.map((e) => {
                    return {
                        // Label for the dataset, usually the category name.
                        label: e.category_name,
                        // Background color for the chart elements associated with this dataset.
                        backgroundColor: e.background,
                        // Border color for the chart line.
                        borderColor: e.color,
                        // The actual monthly count data points for this specific category.
                        data: e.monthly_counts
                    };
                })
        };
    }, page); // Passes the current page number to `getGrowthThreadsData`.
}
setUpDataLine(pageDataLine.value);
const nextDataLine = () => {
    const page = (pageDataLine.value < maxPageDataLine.value || maxPageDataLine.value == 0) ? pageDataLine.value + 1 : pageDataLine.value;
    if (page == pageDataLine.value + 1 && page != maxPageDataLine.value) {
        setUpDataLine(pageDataLine.value);
        pageDataLine.value += 1;
    }
}
const previousDataLine = () => {
    const page = (pageDataLine.value - 1) <= 0 ? 0 : pageDataLine.value - 1;
    if (page > 0) {
        setUpDataLine(pageDataLine.value);
        pageDataLine.value -= 1;
    }
}

const optionsLine = ref({
    responsive: true,
    maintainAspectRatio: false
});

const getPieData = (callback) => {
    api.get(route('dashboard.getCategoryThreadCountsForPieChart')).then((response) => {
        callback(response.data);
    })
}
const dataPie = ref(null);
getPieData((data) => {
    dataPie.value = {
        labels: data.data.map((e) => {
            return e.nameCategory;
        }),
        datasets: [
            {
                backgroundColor: data.data.map((e) => {
                    return e.color;
                }),
                data: data.data.map((e) => {
                    return e.amount;
                })
            }
        ],
    };
});
const optionsPie = ref({
    responsive: true,
    maintainAspectRatio: false
});

const threads = ref(null);
const getTopThread = (callback) => {
    api.get(route('dashboard.getTopThreadsbyPostCount')).then((response) => {
        callback(response.data);
    });
}
getTopThread((data) => {
    threads.value = data.threads;
});

const fetchAllTopics = (callback, page = 1, search = '') => {
    let url = route('categories.index');
    if (search != '') {
        url = url + "?search=" + search;
    }
    url = (search != '') ? url + "&page=" + page : url + "?page=" + page;
    url += '&noParrent=true';

    api.get(url).then((response) => {
        callback(response.data);
    });
}
const topics = ref(null);
fetchAllTopics((data) => {
    topics.value = data;
});
const isSelectCategory = ref(false);
const defaultTopic = "Select a community";
const user = ref(null);
const categorySlug = ref('');
const titleTopic = ref(defaultTopic);
const closeSelectCategories = () => {
    titleTopic.value = defaultTopic;
    categorySlug.value = '';
    user.value = null;
    contentBtnBlock.value = 'Block';
}
const selectCategories = () => {
    isSelectCategory.value = !isSelectCategory.value;
}
const valueSearchUserCategories = ref('');
const searchCategoriesDebounced = debounce(function (value) {
    valueSearchUserCategories.value = value;
    fetchAllTopics((data) => {
        topics.value = data;
    }, 1, value);
}, 500);
const searchCategories = (value) => {
    searchCategoriesDebounced(value);
}

const grantOrRevokeAccess = () => {
    api.post(route('dashboard.grantOrRevokeAccess'), {
        'userId': user.value.user_id,
        'categorySlug': categorySlug.value
    }).then((res) => {
        toast(res.data.message, {
            autoClose: 300,
            "theme": "auto",
            "type": "success",
            "dangerouslyHTMLString": true
        });
    }).catch((err) => {
        console.log(err);
        toast(err.message, {
            autoClose: 300,
            "theme": "auto",
            "type": "error",
            "dangerouslyHTMLString": true
        });
    });
    closeSelectCategories();
}

const blockUserFromCategory = () => {
    api.post(route('dashboard.toggleBlockFromCategory'), {
        'userId': user.value.user_id,
        'categorySlug': categorySlug.value
    }).then((res) => {
        getAllUser((data) => {
            users.value = data.data;
        }, pageUsers.value, userSearch.value);
        toast(res.data.message, {
            autoClose: 300,
            "theme": "auto",
            "type": "success",
            "dangerouslyHTMLString": true
        });
    }).catch((err) => {
        console.log(err);
        toast(err.message, {
            autoClose: 300,
            "theme": "auto",
            "type": "error",
            "dangerouslyHTMLString": true
        });
    });
    closeSelectCategories();
}
const contentBtnBlock = ref('Block');
const selectTopic = (topic) => {
    titleTopic.value = topic.title;
    categorySlug.value = topic.slug;
    clearTimeout(blurTimeout.value);
    isSelectCategory.value = false;
    if (user.value != null) {
        contentBtnBlock.value = 'Block';
        for (let index = 0; index < user.value.blocked_categories.length; index++) {
            const element = user.value.blocked_categories[index];
            if (element.slug == topic.slug) {
                contentBtnBlock.value = 'Allow';
                break;
            }
        }
    }

    searchCategoriesDebounced("");
}
const blurTimeout = ref(null);
const handleBlur = () => {
    // Đặt một timeout để trì hoãn việc ẩn danh sách
    blurTimeout.value = setTimeout(() => {
        isSelectCategory.value = false;
        searchCategoriesDebounced("");
    }, 150); // Điều chỉnh giá trị 150ms theo nhu cầu của bạn
};
const refSearchCategory = ref(null);
const maxPage = ref(0);
const nextCategories = () => {
    clearTimeout(blurTimeout.value);
    refSearchCategory.value.focus();
    const page = (topics.value['current_page'] < maxPage.value || maxPage.value == 0) ? topics.value['current_page'] + 1 : topics.value['current_page'];

    if (page == topics.value['current_page'] + 1 && page != maxPage.value) {
        fetchAllTopics((data) => {
            if (data['data'].length == 0) {
                maxPage.value = data['current_page'];
            } else {
                topics.value = data;
            }
        }, page, valueSearchUserCategories.value ?? '');
    }
}
const previousCategories = () => {
    clearTimeout(blurTimeout.value);
    refSearchCategory.value.focus();
    const page = (topics.value['current_page'] - 1) <= 0 ? 0 : topics.value['current_page'] - 1;

    if (page > 0) {
        fetchAllTopics((data) => {
            topics.value = data;
        }, page, valueSearchUserCategories.value ?? '');
    }
}

const userSearch = ref('');
const users = ref(null);
const pageUsers = ref(1);
const maxUsers = ref(0);
const getAllUser = (callback, page, userSearch) => {
    const url = route('dashboard.getAllUser');
    const urlWithPage = url + '?page=' + page;
    const urlWithSearch = (userSearch != '') ? urlWithPage + '&search=' + userSearch : urlWithPage;
    api.get(urlWithSearch).then((response) => {
        callback(response.data);
    });
}
getAllUser((data) => {
    users.value = data.data;
}, pageUsers.value, userSearch.value);

const searchUserDebounced = debounce(function (value) {
    userSearch.value = value;
    pageUsers.value = 1;
    getAllUser((data) => {
        users.value = data.data;
    }, pageUsers.value, userSearch.value);
}, 500);
const searchUser = (value) => {
    searchUserDebounced(value);
}
const handleBlurUser = () => {
    // Đặt một timeout để trì hoãn việc ẩn danh sách
    blurTimeout.value = setTimeout(() => {
        searchUserDebounced("");
    }, 150); // Điều chỉnh giá trị 150ms theo nhu cầu của bạn
};
const nextUsers = () => {
    const page = (pageUsers.value < maxUsers.value || maxUsers.value == 0) ? pageUsers.value + 1 : pageUsers.value;
    if (page == pageUsers.value + 1 && page != maxUsers.value) {
        getAllUser((data) => {
            if (data.data.length == 0) {
                maxUsers.value = data.pagination.current_page;
            } else {
                users.value = data.data;
                pageUsers.value += 1;
            }
        }, page, userSearch.value);
    }
}
const previousUsers = () => {
    const page = (pageUsers.value <= 0) ? 0 : pageUsers.value - 1;
    if (page > 0) {
        getAllUser((data) => {
            users.value = data.data;
            pageUsers.value -= 1;
        }, page, userSearch.value);
    }
}
const selectUser = (selectedUser) => {
    user.value = selectedUser;
}

onMounted(() => {
    document.addEventListener('keydown', (e) => {
        if (e.key == 'Escape') {
            closeSelectCategories();
        }
    });
});

const getReports = (callback, searchUser, searchThread, currentStatus, page) => {
    let url = route('reports.indexForAdmin') + '?page=' + page;
    if (currentStatus != '') {
        url += '&status=' + currentStatus;
    }
    if (searchUser != '') {
        url += '&user_search=' + searchUser;
    }
    if (searchThread != '') {
        url += '&thread_search=' + searchThread;
    }

    api.get(url).then((res) => {
        callback(res.data.reports);
    }).catch((err) => {

    });
}
const reports = ref(null);
const reportDetail = ref(null);
const maxPageReports = ref(0);
const pageReports = ref(1);
const searchReportUser = ref('');
const searchReportThread = ref('');
const currentStatus = ref('Pending');
getReports((data) => {
    reports.value = data.data;
}, searchReportUser.value, searchReportThread.value, currentStatus.value, pageReports.value);
const nextReports = () => {
    const page = (pageReports.value < maxPageReports.value || maxPageReports.value == 0) ? pageReports.value + 1 : pageReports.value;
    if (page == pageReports.value + 1 && page != maxPageReports.value) {
        getReports((data) => {
            if (data.data.length == 0) {
                maxPageReports.value = data.current_page;
            } else {
                reports.value = data.data;
                pageReports.value += 1;
            }
        }, searchReportUser.value, searchReportThread.value, currentStatus.value, page);
    }
}
const previousReports = () => {
    const page = (pageReports.value <= 0) ? 0 : pageReports.value - 1;
    if (page > 0) {
        getReports((data) => {
            users.value = data.data;
            pageReports.value -= 1;
        }, searchReportUser.value, searchReportThread.value, currentStatus.value, page);
    }
}
const filterReports = (Status) => {
    currentStatus.value = Status;
    pageReports.value = 1;

    getReports((data) => {
        reports.value = data.data;
    }, searchReportUser.value, searchReportThread.value, currentStatus.value, pageReports.value);
}
const searchReportUserDebounced = debounce(function (value) {
    searchReportUser.value = value;
    pageReports.value = 1;
    getReports((data) => {
        reports.value = data.data;
    }, searchReportUser.value, searchReportThread.value, currentStatus.value, pageReports.value);
}, 500);
const searchReportUserFunc = (value) => {
    searchReportUserDebounced(value);
}
const searchReportThreadDebounced = debounce(function (value) {
    searchReportThread.value = value;
    pageReports.value = 1;
    getReports((data) => {
        reports.value = data.data;
    }, searchReportUser.value, searchReportThread.value, currentStatus.value, pageReports.value);
}, 500);
const searchReportThreadFunc = (value) => {
    searchReportThreadDebounced(value);
}
const selectReport = (r) => {
    reportDetail.value = r;
}
const UpdateReport = (newStatus) => {
    if (reportDetail.value != null) {
        api.put(route('reports.update'), {
            status: newStatus,
            thread_id: reportDetail.value.thread_id,
            user_id: reportDetail.value.user_id
        }).then((res) => {
            getReports((data) => {
                reports.value = data.data;
            }, searchReportUser.value, searchReportThread.value, currentStatus.value, pageReports.value);
            toast(res.data.message, {
                autoClose: 300,
                "theme": "auto",
                "type": "success",
                "dangerouslyHTMLString": true
            });
        }).catch((err) => {
            toast(err.message, {
                autoClose: 300,
                "theme": "auto",
                "type": "error",
                "dangerouslyHTMLString": true
            });
        });
    }
}

</script>

<template>
    <DefaultLayout title="Admin-Dashboard">
        <template #search>
        </template>
        <template #search-mobile>
        </template>
        <template #absolute-body>
            <dialog id="setting_modal" class="modal">
                <div class="modal-box">
                    <h3 class="text-lg font-bold">Setting User</h3>
                    <!-- <div class="flex flex-col max-h-[21rem] min-h-[21rem] overflow-auto mt-4 gap-4">
                        <div v-for="i in 8" class="flex justify-between items-center min-w-[18rem] border p-2">
                            <h1>Category {{ i }}</h1>
                            <button class="btn btn-sm btn-error rounded-full">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div> -->

                    <!-- Select Category 1 -->
                    <div class="flex justify-center">
                        <div class="mt-6 flex flex-col w-[21rem]">
                            <InputLabel for="slug-topic" value="Topic" class="mb-1" />
                            <div class="relative flex flex-col ">
                                <div v-if="!isSelectCategory" class="btn btn-outline" @click="selectCategories">
                                    <span>{{ titleTopic }}</span>
                                    <svg rpl="" class="ml-xs" fill="currentColor" height="20"
                                        icon-name="caret-down-outline" viewBox="0 0 20 20" width="20"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M10 13.02a.755.755 0 0 1-.53-.22L4.912 8.242A.771.771 0 0 1 4.93 7.2a.771.771 0 0 1 1.042-.018L10 11.209l4.028-4.027a.771.771 0 0 1 1.042.018.771.771 0 0 1 .018 1.042L10.53 12.8a.754.754 0 0 1-.53.22Z">
                                        </path>
                                    </svg>
                                </div>
                                <label v-if="isSelectCategory" class="input">
                                    <svg class="h-[1em] opacity-50" xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24">
                                        <g stroke-linejoin="round" stroke-linecap="round" stroke-width="2.5" fill="none"
                                            stroke="currentColor">
                                            <circle cx="11" cy="11" r="8"></circle>
                                            <path d="m21 21-4.3-4.3"></path>
                                        </g>
                                    </svg>
                                    <TextInput ref="refSearchCategory"
                                        @input="searchCategories($event.target.value)"
                                        @propertychange="searchCategories($event.target.value)" @blur="handleBlur"
                                        autofocus type="Search" class="grow" :isCustomize="true" />
                                </label>
                                <div v-if="isSelectCategory"
                                    class="absolute z-1 mt-4 p-4 top-10 bg-base-100 shadow-sm rounded-box flex flex-col max-h-[25.75rem] overflow-scroll">
                                    <div class="join">
                                        <div @click="previousCategories" class="join-item btn btn-sm">«</div>
                                        <div class="join-item btn btn-sm">{{ topics['current_page'] }}</div>
                                        <div @click="nextCategories" class="join-item btn btn-sm">»</div>
                                    </div>
                                    <div v-for="item in topics['data']" :key="item.category_id"
                                        class="btn btn-outline mt-2 w-[18rem]" @click="selectTopic(item)">
                                        {{ item.title }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-action">
                        <form method="dialog" class="flex justify-center w-full mt-4 gap-4">
                            <button @click="blockUserFromCategory"
                                :class="{ 'btn-error': contentBtnBlock == 'Block', 'btn-success': contentBtnBlock == 'Allow' }"
                                class="btn">{{ contentBtnBlock }}</button>
                        </form>
                    </div>
                    <div class="modal-action">
                        <form method="dialog">
                            <button @click="closeSelectCategories" class="btn">Close</button>
                        </form>
                    </div>
                </div>
            </dialog>
            <dialog id="modal_report" class="modal">
                <div v-if="reportDetail != null" class="modal-box">
                    <div class="flex flex-col items-center">
                        <h3 class="font-bold text-lg mb-4">Report</h3>
                        <div class="flex flex-col items-start justify-between h-full w-full">
                            <div class="w-full grow overflow-scroll p-2">
                                <p class="font-semibold mb-1">Reason:</p>
                                <p>{{ reportDetail.reason }}</p>
                            </div>
                        </div>
                        <form method="dialog" class="flex justify-center items-center gap-2">
                            <button @click="UpdateReport('Approved')" class="btn btn-sm btn-success">Approve</button>
                            <button @click="UpdateReport('Rejected')" class="btn btn-sm btn-error">Reject</button>
                        </form>
                    </div>
                    <div class="modal-action inline-flex items-center justify-center w-full">
                        <form method="dialog">
                            <button type="submit" class="btn btn-sm btn-soft">Close</button>
                        </form>
                    </div>
                </div>
            </dialog>
        </template>

        <template #body>
            <!-- {{ $page.props.auth.user.role }} -->
            <h1 class="text-xl font-semibold my-2">Dashboard</h1>
            <div class="flex flex-col xl:flex-row justify-around items-center my-4">
                <!-- User Growing -->
                <div class="flex flex-col justify-between items-start">
                    <h1 class="text-lg font-semibold mb-4">User Growing</h1>
                    <div class="w-[21rem] md:w-[26rem] lg:w-[30rem]">
                        <Bar v-if="dataBar" :data="dataBar" :options="optionsBar" />
                    </div>
                </div>
                <div class="flex flex-col justify-between items-start">
                    <h1 class="text-lg font-semibold mb-4">Amount Thread Follow Category</h1>
                    <div class="w-[21rem] lg:w-[24rem]">
                        <Pie v-if="dataPie" :data="dataPie" :options="optionsPie" />
                    </div>
                </div>
            </div>

            <!-- Top 5 Reply -->
            <div class="flex flex-col gap-4">
                <h1 class="text-lg font-semibold mb-2">Top 5 Reply</h1>
                <div v-if="threads" v-for="thread in threads" class="flex justify-between border p-2 rounded-lg">
                    <div class="flex items-center">
                        <div class="max-w-[16rem] max-h-[1.5rem] overflow-auto">
                            <h1 class="font-semibold">{{ thread.title }}</h1>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <p>{{ thread.posts_count }} reply</p>
                    </div>
                </div>
            </div>
            <div class="flex flex-col xl:flex-row justify-around items-center lg:items-start mt-[2rem] gap-4 mb-6">
                <!-- User -->
                <div class="flex flex-col">
                    <div class="flex items-center">
                        <h1 class="text-xl font-semibold">
                            Users
                        </h1>
                        <label class="input ms-2">
                            <svg class="h-[1em] opacity-50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <g stroke-linejoin="round" stroke-linecap="round" stroke-width="2.5" fill="none"
                                    stroke="currentColor">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <path d="m21 21-4.3-4.3"></path>
                                </g>
                            </svg>
                            <TextInput ref="refSearchCategory" @input="searchUser($event.target.value)"
                                @propertychange="searchUser($event.target.value)" @blur="handleBlurUser" autofocus
                                type="Search" class="grow" :isCustomize="true" />
                        </label>
                    </div>
                    <div class="flex flex-col items-center gap-4 mb-8">
                        <div class="my-1"></div>
                        <div v-for="user in users"
                            class="flex justify-between w-[21rem] lg:w-[24rem] border p-2 rounded-lg">
                            <div class="flex items-center">
                                <img class="rounded-full w-12 h-12 object-cover" :src="user.profile_photo_url"
                                    alt="avatar user" />
                                <div class="ms-4">
                                    <h1 class="font-semibold text-xs max-w-[14rem] max-h-[1rem] overflow-auto">{{
                                        user.name }}</h1>
                                    <h1 class="font-semibold text-xs max-w-[14rem] max-h-[1rem] truncate">{{ user.email
                                        }}</h1>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button @click="selectUser(user)" onclick="setting_modal.showModal()"
                                    class="btn rounded-full">
                                    <i class="fa-solid fa-gear"></i>
                                </button>
                            </div>
                        </div>
                        <div class="join">
                            <button @click="previousUsers" class="join-item btn">«</button>
                            <button class="join-item btn">{{ pageUsers }}</button>
                            <button @click="nextUsers" class="join-item btn">»</button>
                        </div>
                    </div>
                </div>
                <!-- Thread growth by category -->
                <div class="flex flex-col justify-center items-start">
                    <h1 class="text-lg font-semibold mb-6">Thread Growing By Category</h1>
                    <div class="w-[21rem] md:w-[26rem] lg:w-[33rem]">
                        <Line v-if="dataLine" :data="dataLine" :options="optionsLine" />
                    </div>
                    <div class="flex justify-center w-full">
                        <div class="join">
                            <button @click="previousDataLine" class="join-item btn">«</button>
                            <button class="join-item btn">{{ pageDataLine }}</button>
                            <button @click="nextDataLine" class="join-item btn">»</button>
                        </div>
                    </div>

                </div>
            </div>

            <div class="flex flex-col">
                <h1 class="text-xl font-semibold mb-2">
                    Reports
                </h1>
                <div class="flex flex-col gap-2 mb-4">
                    <label class="input">
                        <svg class="h-[1em] opacity-50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <g stroke-linejoin="round" stroke-linecap="round" stroke-width="2.5" fill="none"
                                stroke="currentColor">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.3-4.3"></path>
                            </g>
                        </svg>
                        <TextInput placeHolder="User Search" @input="searchReportUserFunc($event.target.value)"
                            @propertychange="searchReportUserFunc($event.target.value)" autofocus type="Search"
                            class="grow" :isCustomize="true" />
                    </label>
                    <label class="input">
                        <svg class="h-[1em] opacity-50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <g stroke-linejoin="round" stroke-linecap="round" stroke-width="2.5" fill="none"
                                stroke="currentColor">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.3-4.3"></path>
                            </g>
                        </svg>
                        <TextInput placeHolder="Thread Search" @input="searchReportThreadFunc($event.target.value)"
                            @propertychange="searchReportThreadFunc($event.target.value)" autofocus type="Search"
                            class="grow" :isCustomize="true" />
                    </label>
                </div>

                <div class="flex items-center gap-2 mb-4">
                    <button @click="filterReports('Approved')" :class="{ 'btn-soft': currentStatus != 'Approved' }"
                        class="btn btn-sm btn-success">Approved</button>
                    <button @click="filterReports('Rejected')" :class="{ 'btn-soft': currentStatus != 'Rejected' }"
                        class="btn btn-sm btn-error">Rejectd</button>
                    <button @click="filterReports('Pending')" :class="{ 'btn-soft': currentStatus != 'Pending' }"
                        class="btn btn-sm btn-info">Pending</button>
                </div>
                <div v-if="reports != null" class="flex flex-col items-center gap-2 mb-2">
                    <div v-for="report in reports"
                        class="flex flex-col lg:flex-row justify-between items-center border w-full p-2 rounded-lg ">
                        <h1 class="font-semibold">{{ report.thread.title }}</h1>
                        <h1 class="font-semibold">{{ report.user.email }}</h1>
                        <button @click="selectReport(report)" onclick="modal_report.showModal()"
                            class="btn btn-sm btn-primary btn-soft rounded-full">
                            <i class="fa-solid fa-info"></i>
                        </button>
                    </div>
                </div>
                <div class="flex justify-center mb-2">
                    <div class="join">
                        <button @click="previousReports" class="join-item btn">«</button>
                        <button class="join-item btn">{{ pageReports }}</button>
                        <button @click="nextReports" class="join-item btn">»</button>
                    </div>
                </div>

            </div>
        </template>

    </DefaultLayout>
</template>
