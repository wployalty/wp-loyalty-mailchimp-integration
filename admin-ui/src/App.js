import React from "react";
import NavbarContentContainer from "./components/containers/NavbarContentContainer";
import {postRequest} from "./components/Common/postRequest";
import LoadingAnimation from "./components/Common/LoadingAnimation";
import {CommonContext, UiLabelContext} from "./Context";
import {alertifyToast, getJSONData, isValidJSON} from "./helpers/utilities";

const App = () => {

    const [appState, setAppState] = React.useState({})
    const [labels, setLabels] = React.useState({})
    const [loading, setLoading] = React.useState(true);

    const [commonState, setCommonState] = React.useState({});
    React.useLayoutEffect(() => {
        const params = {
            action: "wlmi_launcher_local_data",
            wlmi_nonce: wlmi_settings_form.local_data_nonce,
        }
        postRequest(params).then((json) => {
            let resJSON = getJSONData(json.data);
            if (resJSON.success === true && resJSON.data !== null && resJSON.data !== {}) {
                setAppState(resJSON.data);
                let labelParams = {
                    action: "wlmi_get_launcher_labels",
                    wlmi_nonce: resJSON.data.common_nonce,
                }
                postRequest(labelParams).then(json => {
                    let labelsJSON = getJSONData(json.data);
                    if (resJSON !== {}) setLabels(labelsJSON.data);
                    setLoading(false);
                })
            } else if (resJSON.success === false && resJSON.data !== null) {
                alertifyToast(resJSON.message, false);
                setLoading(false);
            }
        });
    }, []);

    return (<div
        className={"bg-grey_extra_light px-5 xl:px-10 py-5 h-full w-full flex items-start justify-start flex-col"}
        style={{
            fontFamily: `Helvetica`
        }}
    >
        {/* Heading Title and version*/}
        {loading ? <LoadingAnimation height={"h-[85vh]"}/>
            : (
                <div className={` w-full `}>
                    <div className={`flex items-baseline  gap-x-3`}>
                        <p className={`text-2xl text-dark font-bold`}>{appState.plugin_name}</p>
                        <span className={`text-base text-extra_light font-medium`}>{appState.version}</span>
                    </div>
                    <CommonContext.Provider value={
                        {
                            commonState, setCommonState,
                            appState, setAppState
                        }
                    }>
                        <UiLabelContext.Provider value={labels}>
                            <NavbarContentContainer/>
                        </UiLabelContext.Provider>
                    </CommonContext.Provider>
                </div>)
        }
    </div>)
};
export default App;
